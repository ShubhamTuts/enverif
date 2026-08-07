<?php

namespace App\Core\Connectors;

use App\Core\Connectors\Contracts\ConnectorDriver;
use App\Core\Plugins\{PluginPresentation,PluginRegistry};
use App\Models\ConnectorConnection;

final class ConnectorManager
{
    /** @var array<string,ConnectorDriver> */
    private array $drivers=[];

    public function __construct(private readonly PluginRegistry $plugins)
    {
        $this->drivers=$plugins->connectorDrivers();
    }

    public function get(string $id):ConnectorDriver
    {
        if(!isset($this->drivers[$id]))throw new \InvalidArgumentException("Unknown connector driver: {$id}");
        return $this->drivers[$id];
    }

    public function all():array{return $this->drivers;}

    /** @return array<int,\App\Core\Connectors\DTO\ConnectorAction> */
    public function actionsFor(ConnectorConnection $connection): array
    {
        $driver=$this->get($connection->driver);
        if(method_exists($driver,'actionsForConnection')){
            /** @var array<int,\App\Core\Connectors\DTO\ConnectorAction> $actions */
            $actions=$driver->actionsForConnection($connection);
            return $actions;
        }
        return $driver->actions();
    }

    public function catalog():array
    {
        $out=[];
        $externalRoot=realpath(base_path('plugins/external'));
        foreach($this->drivers as $id=>$driver){
            $meta=$this->plugins->metadata($id);
            $icon=(string)($meta['icon']??'');
            if($icon==='')$icon=PluginPresentation::iconFor($id);
            elseif(!preg_match('#^https://#i',$icon)){ $release=trim((string)@file_get_contents(base_path('VERSION')))?:'dev'; $icon=url('/plugin-assets/'.rawurlencode((string)($meta['slug']??$id)).'/'.rawurlencode(basename($icon))).'?v='.rawurlencode($release); }
            $developer=(string)($meta['developer']??(method_exists($driver,'developer')?$driver->developer():'Third-party'));
            $directory=realpath((string)($meta['_directory']??''));
            $removable=$externalRoot!==false&&$directory!==false&&str_starts_with($directory,$externalRoot.DIRECTORY_SEPARATOR);
            $out[$id]=[
                'id'=>$id,
                'slug'=>(string)($meta['slug']??$id),
                'label'=>$driver->label(),
                'developer'=>$developer,
                'developer_url'=>$meta['developer_url']??PluginPresentation::developerUrl($developer),
                'homepage'=>$meta['homepage']??null,
                'docs_url'=>$meta['docs_url']??null,
                'category'=>$meta['category']??'Integration',
                'icon'=>$icon,
                'version'=>$meta['version']??null,
                'license'=>$meta['license']??null,
                'removable'=>$removable,
                'schema'=>$driver->configurationSchema(),
                'actions'=>array_map(fn($a)=>[
                    'name'=>$a->name,
                    'description'=>$a->description,
                    'risk'=>$a->risk->value,
                    'parameters'=>$a->parameters,
                    'capabilities'=>$a->capabilities,
                ],$driver->actions()),
            ];
        }
        return $out;
    }
}
