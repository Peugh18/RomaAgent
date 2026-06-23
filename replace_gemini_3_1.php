<?php
function replaceInDirectory($dir)
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php', 'ts', 'vue'])) {
            $content = file_get_contents($file->getPathname());
            
            if (strpos($content, 'gemini-2.5-flash-lite') !== false) {
                $content = str_replace('gemini-2.5-flash-lite', 'gemini-3.1-flash-lite', $content);
                file_put_contents($file->getPathname(), $content);
                echo "Replaced in: " . $file->getPathname() . "\n";
            }
        }
    }
}

replaceInDirectory(__DIR__.'/app');
replaceInDirectory(__DIR__.'/database');
replaceInDirectory(__DIR__.'/tests');
replaceInDirectory(__DIR__.'/resources/js');
echo "Done replacing.\n";
