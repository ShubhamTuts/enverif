<?php
namespace App\Core\Models;
final class MessageNormalizer {
    /** @param list<array<string,mixed>> $messages @return list<array<string,mixed>> */
    public static function openAi(array $messages): array {
        $out=[];
        foreach($messages as $m){$role=(string)($m['role']??'user');if($role==='tool'){$out[]=['role'=>'tool','tool_call_id'=>(string)($m['tool_call_id']??''),'content'=>(string)($m['content']??'')];continue;}$row=['role'=>$role,'content'=>(string)($m['content']??'')];if($role==='assistant'&&!empty($m['tool_calls'])){$row['tool_calls']=array_map(fn($c)=>['id'=>(string)$c['id'],'type'=>'function','function'=>['name'=>(string)$c['name'],'arguments'=>json_encode($c['arguments']??[],JSON_UNESCAPED_SLASHES)]],$m['tool_calls']);if($row['content']==='')$row['content']=null;}$out[]=$row;}return $out;
    }
    /** @param list<array<string,mixed>> $messages @return list<array<string,mixed>> */
    public static function anthropic(array $messages): array {
        $out=[];foreach($messages as $m){$role=(string)($m['role']??'user');if($role==='tool'){$out[]=['role'=>'user','content'=>[['type'=>'tool_result','tool_use_id'=>(string)($m['tool_call_id']??''),'content'=>(string)($m['content']??'')]]];continue;}if($role==='assistant'&&!empty($m['tool_calls'])){$blocks=[];if((string)($m['content']??'')!=='')$blocks[]=['type'=>'text','text'=>(string)$m['content']];foreach($m['tool_calls'] as $c)$blocks[]=['type'=>'tool_use','id'=>(string)$c['id'],'name'=>(string)$c['name'],'input'=>(array)($c['arguments']??[])];$out[]=['role'=>'assistant','content'=>$blocks];continue;}$out[]=['role'=>$role==='assistant'?'assistant':'user','content'=>(string)($m['content']??'')];}return self::mergeAdjacent($out);
    }
    /** @param list<array<string,mixed>> $messages @return list<array<string,mixed>> */
    public static function gemini(array $messages): array {
        $out=[];foreach($messages as $m){$role=(string)($m['role']??'user');$parts=[];if($role==='tool'){$parts[]=['functionResponse'=>['name'=>(string)($m['tool_name']??'tool'),'response'=>['result'=>(string)($m['content']??'')]]];$out[]=['role'=>'user','parts'=>$parts];continue;}if((string)($m['content']??'')!=='')$parts[]=['text'=>(string)$m['content']];if($role==='assistant'&&!empty($m['tool_calls']))foreach($m['tool_calls'] as $c)$parts[]=['functionCall'=>['name'=>(string)$c['name'],'args'=>(array)($c['arguments']??[])]];if($parts)$out[]=['role'=>$role==='assistant'?'model':'user','parts'=>$parts];}return self::mergeAdjacentParts($out);
    }
    private static function mergeAdjacent(array $messages):array{$out=[];foreach($messages as $m){$last=count($out)-1;if($last>=0&&$out[$last]['role']===$m['role']){$a=$out[$last]['content'];$b=$m['content'];$a=is_array($a)?$a:[['type'=>'text','text'=>(string)$a]];$b=is_array($b)?$b:[['type'=>'text','text'=>(string)$b]];$out[$last]['content']=array_merge($a,$b);}else{$out[]=$m;}}return $out;}
    private static function mergeAdjacentParts(array $messages):array{$out=[];foreach($messages as $m){$last=count($out)-1;if($last>=0&&$out[$last]['role']===$m['role'])$out[$last]['parts']=array_merge($out[$last]['parts'],$m['parts']);else$out[]=$m;}return $out;}
}
