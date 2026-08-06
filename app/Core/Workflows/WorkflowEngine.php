<?php

namespace App\Core\Workflows;

use App\Core\Agents\AgentOrchestrator;
use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Connectors\ConnectorManager;
use App\Jobs\ContinueWorkflowRunJob;
use App\Models\{Agent,AgentRun,Approval,Campaign,ConnectorConnection,Lead,LeadActivity,Skill,Workflow,WorkflowRun,WorkflowRunStep};
use App\Support\WorkspaceContext;

final class WorkflowEngine
{
    public function __construct(
        private readonly AgentOrchestrator $agents,
        private readonly ConnectorManager $connectors,
        private readonly WorkspaceContext $workspace,
    ) {}

    /** @param array<string,mixed> $input */
    public function start(Workflow $workflow,string $trigger='manual',array $input=[],string $mode='execute',?string $retryOf=null):WorkflowRun
    {
        $definition=WorkflowDefinitionValidator::validate((array)$workflow->definition);
        if(!in_array($mode,['execute','dry_run'],true))throw new \InvalidArgumentException('Workflow mode must be execute or dry_run.');
        $triggerType=in_array($trigger,WorkflowDefinitionValidator::TRIGGERS,true)?$trigger:'manual';
        $triggerNode=collect($definition['nodes'])->first(fn(array $node):bool=>$node['type']===$triggerType)
            ?? collect($definition['nodes'])->first(fn(array $node):bool=>in_array($node['type'],WorkflowDefinitionValidator::TRIGGERS,true));
        if(!$triggerNode)throw new \RuntimeException('Workflow has no executable trigger.');
        $run=WorkflowRun::create([
            'workspace_id'=>$workflow->workspace_id,'workflow_id'=>$workflow->id,'status'=>'queued','trigger'=>$triggerType,'mode'=>$mode,'retry_of'=>$retryOf,'input'=>$input,'current_node_id'=>$triggerNode['id'],'context'=>[
                'input'=>$input,
                'nodes'=>[],
                'previous'=>null,
                'workflow_definition'=>$definition,
                'workflow_settings'=>(array)$workflow->settings,
                'workflow_version'=>(int)$workflow->version,
            ],'started_at'=>now(),
        ]);
        ContinueWorkflowRunJob::dispatch($run->id);
        return $run;
    }

    public function advance(string $runId):void
    {
        $run=WorkflowRun::withoutGlobalScopes()->with('workflow')->find($runId);
        if(!$run||$this->terminal($run->status))return;
        $this->workspace->set((int)$run->workspace_id);
        $run->refresh()->load('workflow');
        if($run->cancelled_at){$run->update(['status'=>'cancelled','finished_at'=>now()]);return;}

        if($run->status==='awaiting_approval'&&!$this->resumeApproval($run))return;
        if($run->status==='waiting_agent'&&!$this->resumeAgent($run))return;
        if($run->status==='waiting_delay'&&!$this->resumeDelay($run))return;
        if($this->terminal($run->fresh()->status))return;

        $definition=$this->definitionForRun($run);
        $nodes=collect($definition['nodes'])->keyBy('id');
        $processed=0;
        while($run->current_node_id&&$processed<25&&!$this->terminal($run->status)){
            $node=$nodes->get($run->current_node_id);
            if(!$node){$this->fail($run,'Current workflow node no longer exists.');return;}
            $run->update(['status'=>'running']);
            if(!$this->executeNode($run,$node,$definition))return;
            $run->refresh();
            $processed++;
        }
        if(!$run->current_node_id&&!$this->terminal($run->status))$this->finish($run,(array)data_get($run->context,'previous',[]));
        elseif($processed>=25&&!$this->terminal($run->status))ContinueWorkflowRunJob::dispatch($run->id);
    }

    /** @param array<string,mixed> $node @param array<string,mixed> $definition */
    private function executeNode(WorkflowRun $run,array $node,array $definition):bool
    {
        $step=$run->steps()->firstOrCreate(['node_id'=>$node['id']],['node_type'=>$node['type'],'status'=>'pending','input'=>$node['config']??[]]);
        $config=(array)WorkflowValueResolver::resolve((array)($node['config']??[]),(array)$run->context);
        $step->update(['status'=>'running','started_at'=>$step->started_at?:now(),'input'=>$config]);

        try{
            if($run->mode==='dry_run' && !in_array($node['type'],['manual','schedule','webhook','condition','output'],true)){
                return $this->dryRunNode($run,$step,$node,$config,$definition);
            }
            return match($node['type']){
                'manual','schedule','webhook'=>$this->complete($run,$step,['trigger'=>$run->trigger,'input'=>$run->input],$definition),
                'agent'=>$this->executeAgent($run,$step,$config),
                'connector'=>$this->executeConnector($run,$step,$config,$definition,false),
                'skill'=>$this->executeSkill($run,$step,$config,$definition),
                'condition'=>$this->executeCondition($run,$step,$config,$definition),
                'delay'=>$this->executeDelay($run,$step,$config),
                'lead'=>$this->executeLead($run,$step,$config,$definition),
                'campaign'=>$this->executeCampaign($run,$step,$config,$definition),
                'approval'=>$this->requestApproval($run,$step,'workflow.approval',RiskLevel::ExternalWrite,'Workflow requires a human checkpoint.',$config),
                'output'=>$this->completeOutput($run,$step,$config),
                default=>throw new \RuntimeException('Unsupported workflow node.'),
            };
        }catch(\Throwable $e){report($e);$step->update(['status'=>'failed','error'=>$e->getMessage(),'finished_at'=>now()]);$this->fail($run,'Workflow node '.$node['label'].' failed: '.$e->getMessage());return false;}
    }

    private function executeAgent(WorkflowRun $run,WorkflowRunStep $step,array $config):bool
    {
        $agent=Agent::whereKey((int)($config['agent_id']??0))->where('status','active')->firstOrFail();
        $prompt=trim((string)($config['prompt']??data_get($run->context,'previous.prompt',data_get($run->input,'prompt',''))));
        if($prompt==='')$prompt='Continue this revenue workflow using the workflow context: '.json_encode($run->context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $child=$this->agents->start($agent,$prompt,null,['workflow_run_id'=>$run->id,'workflow_node_id'=>$step->node_id]);
        $step->update(['status'=>'waiting_agent','output'=>['child_run_id'=>$child->id]]);$run->update(['status'=>'waiting_agent']);
        ContinueWorkflowRunJob::dispatch($run->id)->delay(now()->addSeconds(8));
        return false;
    }

    private function resumeAgent(WorkflowRun $run):bool
    {
        $step=$run->steps()->where('status','waiting_agent')->latest('id')->first(); if(!$step){$run->update(['status'=>'running']);return true;}
        $childId=(string)data_get($step->output,'child_run_id',''); $child=$childId!==''?AgentRun::withoutGlobalScopes()->where('workspace_id',$run->workspace_id)->find($childId):null;
        if(!$child){$this->fail($run,'Workflow agent run could not be found.');return false;}
        if(!$this->terminal($child->status)){ContinueWorkflowRunJob::dispatch($run->id)->delay(now()->addSeconds(8));return false;}
        if($child->status!=='completed'){$this->fail($run,'Workflow agent ended with status '.$child->status.'.');return false;}
        $definition=$this->definitionForRun($run);
        return $this->complete($run,$step,['child_run_id'=>$child->id,'output'=>$child->output],$definition);
    }

    private function executeConnector(WorkflowRun $run,WorkflowRunStep $step,array $config,array $definition,bool $approved):bool
    {
        $connection=ConnectorConnection::whereKey((int)($config['connection_id']??0))->where('enabled',true)->firstOrFail();
        $driver=$this->connectors->get($connection->driver); $actionName=(string)($config['action']??'');
        $action=collect($driver->actions())->first(fn($a)=>$a->name===$actionName); if(!$action)throw new \InvalidArgumentException('Connector action is unavailable.');
        $settings=$this->settingsForRun($run);$allowExternal=(bool)($settings['allow_external_writes']??false);$allowDestructive=(bool)($settings['allow_destructive']??false);
        if($action->risk===RiskLevel::Destructive&&!$allowDestructive)throw new \RuntimeException('Destructive workflow actions are disabled.');
        $requiresApproval=!$approved&&((bool)($config['requires_approval']??false)||$action->risk===RiskLevel::Secrets||$action->risk===RiskLevel::Destructive||($action->risk===RiskLevel::ExternalWrite&&!$allowExternal));
        if($requiresApproval)return $this->requestApproval($run,$step,'connector.'.$connection->driver.'.'.$actionName,$action->risk,'Workflow requests '.$driver->label().' → '.$actionName.'.',['connection_id'=>$connection->id,'arguments'=>(array)($config['arguments']??[])]);
        $result=$driver->execute($connection,$actionName,(array)($config['arguments']??[])); if(!$result->ok)throw new \RuntimeException($result->message?:'Connector action failed.');
        return $this->complete($run,$step,['ok'=>true,'data'=>$result->data],$definition);
    }

    private function requestApproval(WorkflowRun $run,WorkflowRunStep $step,string $action,RiskLevel $risk,string $summary,array $payload):bool
    {
        $step->update(['status'=>'awaiting_approval']);
        Approval::firstOrCreate(['workspace_id'=>$run->workspace_id,'workflow_run_step_id'=>$step->id,'status'=>'pending'],['workflow_run_id'=>$run->id,'action'=>$action,'risk_level'=>$risk->value,'summary'=>$summary,'payload'=>$payload]);
        $run->update(['status'=>'awaiting_approval']);return false;
    }

    private function resumeApproval(WorkflowRun $run):bool
    {
        $step=$run->steps()->where('status','awaiting_approval')->latest('id')->first(); if(!$step){$run->update(['status'=>'running']);return true;}
        $approval=Approval::where('workflow_run_step_id',$step->id)->latest('id')->first(); if(!$approval||$approval->status==='pending')return false;
        if($approval->status!=='approved'){$step->update(['status'=>'failed','error'=>'Human approval denied.','finished_at'=>now()]);$this->fail($run,'Workflow action was denied.');return false;}
        $definition=$this->definitionForRun($run);$node=collect($definition['nodes'])->firstWhere('id',$step->node_id);if(!$node){$this->fail($run,'Approved workflow node no longer exists.');return false;}
        $config=(array)WorkflowValueResolver::resolve((array)($node['config']??[]),(array)$run->context);
        if($node['type']==='connector')return $this->executeConnector($run,$step,$config,$definition,true);
        return $this->complete($run,$step,['approved'=>true,'approval_id'=>$approval->id],$definition);
    }

    private function executeSkill(WorkflowRun $run,WorkflowRunStep $step,array $config,array $definition):bool
    {
        $skill=Skill::whereKey((int)($config['skill_id']??0))->where(fn($q)=>$q->whereNull('workspace_id')->orWhere('workspace_id',$run->workspace_id))->where('status','active')->firstOrFail();
        $agentId=(int)($config['agent_id']??0);
        $agent=$agentId>0?Agent::whereKey($agentId)->where('status','active')->first():Agent::where('status','active')->first();
        if(!$agent)throw new \RuntimeException('Skill workflow nodes require at least one active agent.');
        $prompt=trim((string)($config['prompt']??data_get($run->input,'prompt','')));
        if($prompt==='')$prompt='Apply the selected skill to the current workflow context and return the actionable result.';
        $child=$this->agents->start($agent,$prompt,null,[
            'workflow_run_id'=>$run->id,
            'workflow_node_id'=>$step->node_id,
            'selected_skill_ids'=>[$skill->id],
            'workflow_context'=>$run->context,
        ]);
        $step->update(['status'=>'waiting_agent','output'=>['child_run_id'=>$child->id,'skill_id'=>$skill->id]]);
        $run->update(['status'=>'waiting_agent']);
        ContinueWorkflowRunJob::dispatch($run->id)->delay(now()->addSeconds(8));
        return false;
    }

    /** @param array<string,mixed> $node @param array<string,mixed> $config @param array<string,mixed> $definition */
    private function dryRunNode(WorkflowRun $run,WorkflowRunStep $step,array $node,array $config,array $definition):bool
    {
        $output=['dry_run'=>true,'node_type'=>$node['type'],'validated'=>true];
        if($node['type']==='agent'){
            $agent=Agent::whereKey((int)($config['agent_id']??0))->where('status','active')->firstOrFail();
            $output+=['agent_id'=>$agent->id,'agent'=>$agent->name];
        }elseif($node['type']==='skill'){
            $skill=Skill::whereKey((int)($config['skill_id']??0))->where('status','active')->firstOrFail();
            $output+=['skill_id'=>$skill->id,'skill'=>$skill->name];
        }elseif($node['type']==='connector'){
            $connection=ConnectorConnection::whereKey((int)($config['connection_id']??0))->where('enabled',true)->firstOrFail();
            $driver=$this->connectors->get($connection->driver);$action=(string)($config['action']??'');
            if(!collect($driver->actions())->contains(fn($item)=>$item->name===$action))throw new \RuntimeException('Connector action is unavailable.');
            $output+=['connection_id'=>$connection->id,'driver'=>$connection->driver,'action'=>$action];
        }elseif($node['type']==='campaign'){
            $campaign=Campaign::findOrFail((int)($config['campaign_id']??0));$output+=['campaign_id'=>$campaign->id,'campaign'=>$campaign->name];
        }elseif($node['type']==='lead'){
            $output+=['operation'=>(string)($config['operation']??'upsert')];
        }elseif($node['type']==='delay'){
            $output+=['seconds'=>max(1,min(604800,(int)($config['seconds']??1)))];
        }elseif($node['type']==='approval'){
            $output+=['approval'=>'simulated'];
        }
        return $this->complete($run,$step,$output,$definition);
    }

    private function executeCondition(WorkflowRun $run,WorkflowRunStep $step,array $config,array $definition):bool
    {
        $path=(string)$config['path'];$left=WorkflowValueResolver::resolve('{{'.$path.'}}',(array)$run->context);$right=$config['value']??null;$op=(string)$config['operator'];
        $result=match($op){'equals'=>$left==$right,'not_equals'=>$left!=$right,'contains'=>is_string($left)&&str_contains(mb_strtolower($left),mb_strtolower((string)$right)),'gt'=>(float)$left>(float)$right,'gte'=>(float)$left>=(float)$right,'lt'=>(float)$left<(float)$right,'lte'=>(float)$left<=(float)$right,'exists'=>$left!==null&&$left!=='',default=>false};
        return $this->complete($run,$step,['result'=>$result,'left'=>$left,'right'=>$right],$definition,$result?'true':'false');
    }

    private function executeDelay(WorkflowRun $run,WorkflowRunStep $step,array $config):bool
    {
        $seconds=max(1,min(604800,(int)($config['seconds']??1)));$resumeAt=now()->addSeconds($seconds);$step->update(['status'=>'waiting_delay','output'=>['resume_at'=>$resumeAt->toIso8601String()]]);$run->update(['status'=>'waiting_delay']);ContinueWorkflowRunJob::dispatch($run->id)->delay($resumeAt);return false;
    }
    private function resumeDelay(WorkflowRun $run):bool
    {
        $step=$run->steps()->where('status','waiting_delay')->latest('id')->first();if(!$step){$run->update(['status'=>'running']);return true;}$resume=strtotime((string)data_get($step->output,'resume_at',''));if($resume>time()){ContinueWorkflowRunJob::dispatch($run->id)->delay(now()->addSeconds(max(1,$resume-time())));return false;}$definition=$this->definitionForRun($run);return $this->complete($run,$step,['resumed_at'=>now()->toIso8601String()],$definition);
    }

    private function executeLead(WorkflowRun $run,WorkflowRunStep $step,array $config,array $definition):bool
    {
        $operation=(string)($config['operation']??'upsert');
        if($operation==='activity'){$lead=Lead::findOrFail((int)($config['lead_id']??0));$activity=LeadActivity::create(['lead_id'=>$lead->id,'type'=>(string)($config['type']??'workflow'),'summary'=>(string)($config['summary']??'Workflow activity'),'data'=>['workflow_run_id'=>$run->id]]);return $this->complete($run,$step,['lead_id'=>$lead->id,'activity_id'=>$activity->id],$definition);}
        $sourceFields=is_array($config['fields']??null)?$config['fields']:$config;$email=trim((string)($sourceFields['email']??''));$website=trim((string)($sourceFields['website']??''));if($email===''&&$website==='')throw new \InvalidArgumentException('Lead upsert requires email or website.');
        $query=Lead::query();$lead=$email!==''?$query->where('email',$email)->first():$query->where('website',$website)->first();$fields=array_intersect_key($sourceFields,array_flip(['first_name','last_name','email','phone','title','company','website','linkedin_url','city','country','status','score','source','source_url','research_summary']));
        if($lead)$lead->update($fields);else{$fields['workspace_id']=$run->workspace_id;$lead=Lead::create($fields);}return $this->complete($run,$step,['lead_id'=>$lead->id,'email'=>$lead->email,'company'=>$lead->company],$definition);
    }

    private function executeCampaign(WorkflowRun $run,WorkflowRunStep $step,array $config,array $definition):bool
    {
        $campaign=Campaign::findOrFail((int)($config['campaign_id']??0));$leadId=(int)($config['lead_id']??0);if($leadId<=0)$leadId=(int)data_get($run->context,'previous.lead_id',0);$lead=Lead::findOrFail($leadId);$campaign->leads()->syncWithoutDetaching([$lead->id=>['status'=>'queued','current_step'=>0,'next_action_at'=>now()]]);return $this->complete($run,$step,['campaign_id'=>$campaign->id,'lead_id'=>$lead->id,'added'=>true],$definition);
    }

    private function completeOutput(WorkflowRun $run,WorkflowRunStep $step,array $config):bool
    {
        $value=$config['value']??data_get($run->context,'previous',[]);$output=$this->normalize($value);$step->update(['status'=>'completed','output'=>$output,'finished_at'=>now()]);$this->recordContext($run,$step->node_id,$output);$this->finish($run,$output);return false;
    }

    private function complete(WorkflowRun $run,WorkflowRunStep $step,mixed $output,array $definition,string $port='default'):bool
    {
        $normalized=$this->normalize($output);$step->update(['status'=>'completed','output'=>$normalized,'error'=>null,'finished_at'=>now()]);$this->recordContext($run,$step->node_id,$normalized);$next=$this->nextNode($definition,$step->node_id,$port);if($next===null){$run->update(['current_node_id'=>null]);$this->finish($run,$normalized);return false;}$run->update(['current_node_id'=>$next,'status'=>'running']);return true;
    }
    private function recordContext(WorkflowRun $run,string $nodeId,array $output):void{$context=(array)$run->context;$context['nodes'][$nodeId]=$output;$context['previous']=$output;$run->update(['context'=>$context]);}
    private function nextNode(array $definition,string $from,string $port):?string{$edges=array_values(array_filter($definition['edges'],fn(array $e):bool=>$e['from']===$from));if($edges===[])return null;$exact=collect($edges)->firstWhere('port',$port);if($exact)return (string)$exact['to'];$default=collect($edges)->firstWhere('port','default');return $default?(string)$default['to']:(string)$edges[0]['to'];}
    /** @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} */
    private function definitionForRun(WorkflowRun $run): array
    {
        $snapshot = data_get($run->context, 'workflow_definition');
        return WorkflowDefinitionValidator::validate(is_array($snapshot) ? $snapshot : (array) $run->workflow->definition);
    }

    /** @return array<string,mixed> */
    private function settingsForRun(WorkflowRun $run): array
    {
        $snapshot = data_get($run->context, 'workflow_settings');
        return is_array($snapshot) ? $snapshot : (array) $run->workflow->settings;
    }

    private function normalize(mixed $value):array{return is_array($value)?$value:['value'=>$value];}
    private function finish(WorkflowRun $run,array $output):void{$run->update(['status'=>'completed','output'=>$output,'current_node_id'=>null,'finished_at'=>now(),'error'=>null]);}
    private function fail(WorkflowRun $run,string $message):void{$run->update(['status'=>'failed','error'=>$message,'finished_at'=>now()]);}
    private function terminal(string $status):bool{return in_array($status,['completed','failed','cancelled'],true);}
}
