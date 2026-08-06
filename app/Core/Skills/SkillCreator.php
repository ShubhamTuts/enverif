<?php

namespace App\Core\Skills;

use App\Models\Skill;
use Illuminate\Support\Str;

final class SkillCreator
{
    public function create(int $workspaceId, array $data): Skill
    {
        return $this->persist($workspaceId, $data);
    }

    public function update(Skill $skill, int $workspaceId, array $data): Skill
    {
        if ((int) $skill->workspace_id !== $workspaceId || $skill->built_in) {
            throw new \InvalidArgumentException('This skill cannot be modified in the current workspace.');
        }
        return $this->persist($workspaceId, $data, $skill);
    }

    private function persist(int $workspaceId, array $data, ?Skill $existing = null): Skill
    {
        $slug = Str::slug((string) $data['name']);
        if ($slug === '') {
            throw new \InvalidArgumentException('Skill name must contain at least one letter or number.');
        }
        $content = "---\nname: {$slug}\ndescription: " . trim((string) ($data['description'] ?? '')) .
            "\nversion: " . ($data['version'] ?? '1.0.0') .
            "\ncapabilities: [" . implode(', ', (array) ($data['capabilities'] ?? ['read'])) . "]\n---\n" .
            trim((string) $data['body']);
        $parsed = SkillFrontmatter::parse($content);
        $scan = (new SkillSecurityScanner)->scan($content);
        $values = [
            'workspace_id' => $workspaceId,
            'name' => $parsed['name'],
            'slug' => $parsed['name'],
            'description' => $parsed['description'],
            'version' => $parsed['version'],
            'source_type' => 'local',
            'source_url' => null,
            'source_ref' => null,
            'checksum' => SourceProvenanceValidator::checksum($content),
            'license' => 'MIT',
            'capabilities' => $parsed['capabilities'],
            'body' => $parsed['body'],
            'status' => $scan['safe'] ? 'active' : 'quarantined',
            'built_in' => false,
        ];

        if ($existing) {
            $existing->update($values);
            return $existing->refresh();
        }
        return Skill::create($values);
    }
}
