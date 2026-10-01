<?php
$dir = __DIR__ . '/View';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$count = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && (pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'php' || pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'js')) {
        $content = file_get_contents($file->getPathname());
        $original = $content;
        
        // Replace $.post('router.php to $.post('router
        $content = preg_replace("/\\\$\.post\(\s*['\"]([^'\"]*)router\.php/i", "$.post('$1router", $content);
        
        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            $count++;
            echo "Updated: " . $file->getFilename() . "\n";
        }
    }
}
echo "Total updated: $count\n";
