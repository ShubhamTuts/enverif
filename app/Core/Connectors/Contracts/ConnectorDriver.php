<?php
namespace App\Core\Connectors\Contracts;
use App\Core\Connectors\DTO\ConnectorAction;use App\Core\Connectors\DTO\ConnectorResult;use App\Models\ConnectorConnection;
interface ConnectorDriver {public function id():string;public function label():string;/** @return list<ConnectorAction> */public function actions():array;/** @return array<string,mixed> */public function configurationSchema():array;public function test(ConnectorConnection $connection):bool;/** @param array<string,mixed> $arguments */public function execute(ConnectorConnection $connection,string $action,array $arguments):ConnectorResult;}
