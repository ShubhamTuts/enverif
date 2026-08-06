<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/site';
$errors = [];
$count = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') continue;
    $html = (string) file_get_contents($file->getPathname());
    if (!preg_match_all('/(?:href|src)="([^"]+)"/', $html, $matches)) continue;
    foreach ($matches[1] as $url) {
        if ($url === '' || str_starts_with($url, '#') || preg_match('#^(?:https?:|mailto:|tel:|data:|javascript:)#i', $url)) continue;
        $count++;
        $path = explode('#', $url, 2)[0];
        $target = realpath($file->getPath() . '/' . $path);
        if ($target === false || !str_starts_with(str_replace('\\', '/', $target), str_replace('\\', '/', realpath($root) ?: $root))) {
            $errors[] = str_replace($root . '/', '', $file->getPathname()) . " -> {$url}";
        }
    }
}
if ($errors !== []) {
    fwrite(STDERR, "Broken static site links:\n- " . implode("\n- ", array_unique($errors)) . "\n");
    exit(1);
}
echo "Static site link check passed ({$count} local asset/page references).\n";
