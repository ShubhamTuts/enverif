<?php
namespace App\Core\Agents\Tools\DTO;
final class ToolExecutionResult {public function __construct(public readonly bool $ok,public readonly mixed $data,public readonly ?string $message=null){}public static function success(mixed $data):self{return new self(true,$data);}public static function failure(string $message,mixed $data=null):self{return new self(false,$data,$message);}public function text():string{$value=$this->ok?$this->data:['error'=>$this->message,'data'=>$this->data];return is_string($value)?$value:(json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'null');}}
