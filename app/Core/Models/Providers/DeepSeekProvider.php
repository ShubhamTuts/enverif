<?php

namespace App\Core\Models\Providers;

use App\Core\Models\AttachmentPayload;
use App\Core\Models\DTO\{ModelRequest,ModelResponse,ToolCall};
use App\Core\Models\MessageNormalizer;
use App\Models\ModelConnection;

final class DeepSeekProvider extends AbstractHttpProvider
{
    public function id():string{return 'deepseek';}
    public function models():array{return ['deepseek-chat','deepseek-reasoner'];}
    public function complete(ModelConnection $connection,ModelRequest $request):ModelResponse
    {
        $base=rtrim($connection->base_url?:'https://api.deepseek.com','/');$messages=array_merge([['role'=>'system','content'=>$request->system]],MessageNormalizer::openAi($request->messages));$attachmentText=[];
        foreach($request->attachments as $attachment){if(!is_array($attachment))continue;$text=AttachmentPayload::text($attachment);$label=AttachmentPayload::label($attachment);$attachmentText[]=$text!==null?"Attachment {$label}:\n{$text}":"Attachment {$label} is not text-readable by this provider; use its filename/type as context only.";}
        if($attachmentText)$messages[]=['role'=>'user','content'=>implode("\n\n",$attachmentText)];
        $payload=['model'=>$request->model,'messages'=>$messages,'max_tokens'=>$request->maxTokens];if($request->tools)$payload['tools']=array_map(fn($t)=>['type'=>'function','function'=>['name'=>$t['name'],'description'=>$t['description']??'','parameters'=>$t['parameters']??['type'=>'object','properties'=>[]]]],$request->tools);
        $res=$this->client($connection,['Authorization'=>'Bearer '.$this->apiKey($connection)])->post($base.'/chat/completions',$payload)->throw()->json();$msg=$res['choices'][0]['message']??[];$calls=[];
        foreach(($msg['tool_calls']??[]) as $call){$args=json_decode((string)($call['function']['arguments']??'{}'),true);$calls[]=new ToolCall((string)($call['id']??uniqid('tool_',true)),(string)($call['function']['name']??''),is_array($args)?$args:[]);}
        return new ModelResponse((string)($msg['content']??''),$calls,(int)($res['usage']['prompt_tokens']??0),(int)($res['usage']['completion_tokens']??0),(string)($res['choices'][0]['finish_reason']??''));
    }
}
