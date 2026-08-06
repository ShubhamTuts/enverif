<?php

namespace App\Core\Models\Providers;

use App\Core\Models\AttachmentPayload;
use App\Core\Models\DTO\{ModelRequest,ModelResponse,ToolCall};
use App\Core\Models\MessageNormalizer;
use App\Models\ModelConnection;

final class OpenAIProvider extends AbstractHttpProvider
{
    public function id():string{return 'openai';}
    public function models():array{return ['gpt-5','gpt-5-mini','gpt-4.1'];}

    public function complete(ModelConnection $connection,ModelRequest $request):ModelResponse
    {
        $base=rtrim($connection->base_url?:'https://api.openai.com','/');
        $messages=array_merge([['role'=>'system','content'=>$request->system]],MessageNormalizer::openAi($request->messages));
        $attachmentContent=[];
        foreach($request->attachments as $attachment){
            if(!is_array($attachment))continue;
            $text=AttachmentPayload::text($attachment);
            if($text!==null){$attachmentContent[]=['type'=>'text','text'=>'Attachment '.AttachmentPayload::label($attachment).":\n".$text];continue;}
            if(AttachmentPayload::isImage($attachment)){
                $data=AttachmentPayload::base64($attachment);$mime=(string)($attachment['mime_type']??'image/png');
                if($data!==null)$attachmentContent[]=['type'=>'image_url','image_url'=>['url'=>'data:'.$mime.';base64,'.$data]];
            }
        }
        if($attachmentContent)$messages[]=['role'=>'user','content'=>$attachmentContent];
        $payload=['model'=>$request->model,'messages'=>$messages,'max_completion_tokens'=>$request->maxTokens];
        if(str_starts_with($request->model,'gpt-5'))$payload['reasoning_effort']=match($request->effort){'fast'=>'minimal','deep'=>'high',default=>'medium'};
        if($request->tools)$payload['tools']=$this->tools($request->tools);
        $res=$this->client($connection,['Authorization'=>'Bearer '.$this->apiKey($connection)])->post($base.'/v1/chat/completions',$payload)->throw()->json();
        $msg=$res['choices'][0]['message']??[];$calls=[];
        foreach(($msg['tool_calls']??[]) as $call){$args=json_decode((string)($call['function']['arguments']??'{}'),true);$calls[]=new ToolCall((string)($call['id']??uniqid('tool_',true)),(string)($call['function']['name']??''),is_array($args)?$args:[]);}
        return new ModelResponse((string)($msg['content']??''),$calls,(int)($res['usage']['prompt_tokens']??0),(int)($res['usage']['completion_tokens']??0),(string)($res['choices'][0]['finish_reason']??''));
    }
    private function tools(array $tools):array{return array_map(fn($t)=>['type'=>'function','function'=>['name'=>$t['name'],'description'=>$t['description']??'','parameters'=>$t['parameters']??['type'=>'object','properties'=>[]]]],$tools);}
}
