<?php

namespace App\Http\Controllers;

use App\Core\Workflows\{WorkflowDefinitionValidator,WorkflowEngine,WorkflowRuntimeValidator};
use App\Core\Connectors\ConnectorManager;
use App\Core\Runtime\WebQueueKick;
use App\Models\{Agent,Campaign,ConnectorConnection,Skill,Workflow};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WorkflowController extends Controller
{
    public function __construct(private readonly ConnectorManager $connectorManager,private readonly WorkflowRuntimeValidator $runtimeValidator) {}
    public function index(Request $request){$query=Workflow::withCount('runs')->latest();if($request->filled('status'))$query->where('status',$request->string('status'));if($request->filled('q'))$query->where('name','like','%'.$request->string('q').'%');return view('workflows.index',['workflows'=>$query->paginate(18)->withQueryString()]);}
    public function create(){return view('workflows.form',$this->formData(new Workflow(['definition'=>$this->defaultDefinition(),'status'=>'draft'])));}
    public function store(Request $request){$data=$this->data($request);$workflow=Workflow::create($data+['webhook_secret'=>Str::random(48),'version'=>1]);$this->assertActivation($workflow);return redirect()->route('workflows.show',$workflow)->with('status','Workflow created.');}
    public function show(Workflow $workflow){return view('workflows.show',['workflow'=>$workflow,'runs'=>$workflow->runs()->latest()->paginate(20),'runtimeErrors'=>$this->runtimeValidator->validate($workflow)]);}
    public function edit(Workflow $workflow){return view('workflows.form',$this->formData($workflow));}
    public function update(Request $request,Workflow $workflow){$data=$this->data($request);$data['version']=$workflow->version+1;$workflow->update($data);$this->assertActivation($workflow);return redirect()->route('workflows.show',$workflow)->with('status','Workflow updated.');}
    public function destroy(Workflow $workflow){$workflow->delete();return redirect()->route('workflows.index')->with('status',__('ui.deleted'));}
    public function run(Request $request,Workflow $workflow,WorkflowEngine $engine,WebQueueKick $queueKick){$this->runtimeValidator->assertExecutable($workflow);$input=$this->runInput($request);$run=$engine->start($workflow,'manual',$input,'execute');$queueKick->afterResponse();return redirect()->route('workflow-runs.show',$run);}
    public function test(Request $request,Workflow $workflow,WorkflowEngine $engine,WebQueueKick $queueKick){$this->runtimeValidator->assertExecutable($workflow);$input=$this->runInput($request);$run=$engine->start($workflow,'manual',$input,'dry_run');$queueKick->afterResponse();return redirect()->route('workflow-runs.show',$run)->with('status','Dry run started. External effects are simulated.');}
    public function regenerateWebhook(Workflow $workflow){$workflow->update(['webhook_secret'=>Str::random(48)]);return back()->with('status','Webhook secret regenerated.');}

    private function data(Request $request):array
    {
        $raw=$request->validate(['name'=>'required|string|max:120','description'=>'nullable|string|max:2000','status'=>'required|in:draft,active,paused','definition'=>'required|string|max:200000','allow_external_writes'=>'nullable|boolean','allow_destructive'=>'nullable|boolean']);
        try{$definition=WorkflowDefinitionValidator::validate(json_decode($raw['definition'],true,512,JSON_THROW_ON_ERROR));}catch(\Throwable $e){throw ValidationException::withMessages(['definition'=>$e->getMessage()]);}
        return ['name'=>$raw['name'],'description'=>$raw['description']??null,'status'=>$raw['status'],'definition'=>$definition,'settings'=>['allow_external_writes'=>$request->boolean('allow_external_writes'),'allow_destructive'=>$request->boolean('allow_destructive')]];
    }
    private function runInput(Request $request):array
    {
        $data=$request->validate(['prompt'=>'nullable|string|max:20000','input_json'=>'nullable|string|max:50000']);$input=[];
        if(trim((string)($data['input_json']??''))!==''){$input=json_decode((string)$data['input_json'],true);if(!is_array($input))throw ValidationException::withMessages(['input_json'=>'Input JSON must be an object.']);}
        if(trim((string)($data['prompt']??''))!=='')$input['prompt']=trim((string)$data['prompt']);return $input;
    }
    private function assertActivation(Workflow $workflow):void
    {
        if($workflow->status!=='active')return;$errors=$this->runtimeValidator->validate($workflow);if($errors){$workflow->update(['status'=>'draft']);throw ValidationException::withMessages(['definition'=>'Workflow stayed in Draft because runtime validation failed: '.implode(' ',$errors)]);}
    }
    private function formData(Workflow $workflow):array{return ['workflow'=>$workflow,'agents'=>Agent::where('status','active')->orderBy('name')->get(['id','name']),'connectors'=>ConnectorConnection::where('enabled',true)->orderBy('name')->get(['id','name','driver']),'skills'=>Skill::where(fn($q)=>$q->whereNull('workspace_id')->orWhere('workspace_id',session('workspace_id')))->where('status','active')->orderBy('name')->get(['id','name']),'campaigns'=>Campaign::orderBy('name')->get(['id','name']),'connectorCatalog'=>$this->connectorManager->catalog()];}
    /** Starter canvas: one Manual trigger only — operators drag additional nodes from the palette. */
    private function defaultDefinition(): array
    {
        return [
            'nodes' => [[
                'id' => 'trigger_1',
                'type' => 'manual',
                'label' => 'Manual trigger',
                'config' => [],
                'position' => ['x' => 120, 'y' => 180],
            ]],
            'edges' => [],
        ];
    }
}
