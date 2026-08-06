<?php

namespace App\Core\Models\Providers;

use App\Core\Models\AttachmentPayload;
use App\Core\Models\DTO\{ModelRequest,ModelResponse,ToolCall};
use App\Core\Models\MessageNormalizer;
use App\Models\ModelConnection;

final class GeminiProvider extends AbstractHttpProvider
{
    public function id():string{return 'gemini';}
    public function models():array{return ['gemini-2.5-pro','gemini-2.5-flash','gemini-2.5-flash-lite'];}
    public function complete(ModelConnection $connection,ModelRequest $request):ModelResponse
    {
        $base=rtrim($connection->base_url?:'https://generativelanguage.googleapis.com','/');$contents=MessageNormalizer::gemini($request->messages);$parts=[];
        foreach($request->attachments as $attachment){
            if(!is_array($attachment))continue;$text=AttachmentPayload::text($attachment);
            if($text!==null){$parts[]=['text'=>'Attachment '.AttachmentPayload::label($attachment).":\n".$text];continue;}
            if(AttachmentPayload::isImage($attachment)){$data=AttachmentPayload::base64($attachment);$mime=(string)($attachment['mime_type']??'image/png');if($data!==null)$parts[]=['inlineData'=>['mimeType'=>$mime,'data'=>$data]];}
        }
        if($parts)$contents[]=['role'=>'user','parts'=>$parts];
        $payload=['systemInstruction'=>['parts'=>[['text'=>$request->system]]],'contents'=>$contents,'generationConfig'=>['maxOutputTokens'=>$request->maxTokens]];
        if($request->tools)$payload['tools']=[['functionDeclarations'=>array_map(fn($t)=>['name'=>$t['name'],'description'=>$t['description']??'','parameters'=>$t['parameters']??['type'=>'object','properties'=>[]]],$request->tools)]];
        $url=$base.'/v1beta/models/'.rawurlencode($request->model).':generateContent?key='.rawurlencode($this->apiKey($connection));$res=$this->client($connection)->post($url,$payload)->throw()->json();$text='';$calls=[];
        foreach(($res['candidates'][0]['content']['parts']??[]) as $part){if(isset($part['text']))$text.=(string)$part['text'];if(isset($part['functionCall'])){$fc=$part['functionCall'];$calls[]=new ToolCall(uniqid('gemini_',true),(string)($fc['name']??''),is_array($fc['args']??null)?$fc['args']:[]);}}
        $usage=$res['usageMetadata']??[];return new ModelResponse($text,$calls,(int)($usage['promptTokenCount']??0),(int)($usage['candidatesTokenCount']??0),(string)($res['candidates'][0]['finishReason']??''));
    }
}
