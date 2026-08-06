<?php
namespace App\Support;
final class WorkspaceContext { private ?int $workspaceId=null; public function set(?int $id):void{$this->workspaceId=$id;} public function id():?int{return $this->workspaceId;} public function has():bool{return $this->workspaceId!==null;} }
