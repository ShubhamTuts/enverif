<?php
namespace App\Core\Models\Contracts;
use App\Core\Models\DTO\ModelRequest;use App\Core\Models\DTO\ModelResponse;use App\Models\ModelConnection;
interface ModelProvider {public function id():string;/** @return list<string> */public function models():array;public function complete(ModelConnection $connection,ModelRequest $request):ModelResponse;public function test(ModelConnection $connection):bool;}
