<?php
namespace App\Core\Agents;use App\Models\AgentRun;
final class RunAuditor {/** @return array{ok:bool,issues:list<string>} */public function audit(AgentRun $run):array{$issues=[];if($run->status==='completed'&&trim((string)$run->output)==='')$issues[]='Completed run has no output.';if($run->steps()->where('status','failed')->exists())$issues[]='One or more tool steps failed.';if($run->steps()->where('status','awaiting_approval')->exists())$issues[]='Run still has an unresolved approval.';return ['ok'=>$issues===[],'issues'=>$issues];}}
