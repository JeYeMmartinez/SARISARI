<?php
function replaceLocationHref($dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            $content = file_get_contents($path);
            $changed = false;
            
            // Fix header locations
            if (preg_match('/header\("Location:\s*([^"]+)\.php"\);/', $content)) {
                $content = preg_replace('/header\("Location:\s*([^"]+)\.php"\);/', 'header("Location: $1");', $content);
                $changed = true;
            }
            
            // Fix window.location.href
            if (preg_match('/window\.location\.href\s*=\s*[\'"]([^\'"]+)\.php[\'"];/', $content)) {
                $content = preg_replace('/window\.location\.href\s*=\s*[\'"]([^\'"]+)\.php[\'"];/', 'window.location.href = \'$1\';', $content);
                $changed = true;
            }

            if ($changed) {
                file_put_contents($path, $content);
                echo "Updated $path\n";
            }
        }
    }
}

replaceLocationHref('View');
?>
