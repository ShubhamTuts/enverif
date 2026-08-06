<?php
namespace App\Core\Models;
use App\Core\Models\Contracts\ModelProvider;use App\Core\Models\Providers\{AnthropicProvider,DeepSeekProvider,GeminiProvider,OpenAIProvider};
final class ProviderManager {/** @var array<string,ModelProvider> */private array $providers=[];public function __construct(){foreach([new OpenAIProvider,new AnthropicProvider,new GeminiProvider,new DeepSeekProvider] as $p)$this->providers[$p->id()]=$p;}public function get(string $id):ModelProvider{if(!isset($this->providers[$id]))throw new \InvalidArgumentException("Unknown model provider: {$id}");return $this->providers[$id];}public function all():array{return $this->providers;}public function catalog():array{$out=[];foreach($this->providers as $id=>$p)$out[$id]=$p->models();return $out;}}
