<?php
namespace App\Core\Connectors\DTO;
final class ConnectorResult {/** @param mixed $data */public function __construct(public readonly bool $ok,public readonly mixed $data,public readonly ?string $message=null){}public static function success(mixed $data):self{return new self(true,$data);}public static function failure(string $message,mixed $data=null):self{return new self(false,$data,$message);}}
