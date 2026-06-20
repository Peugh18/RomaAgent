<?php

function replaceInDirectory($dir)
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());

            // Replace 'gemini-2.5-flash' with 'gemini-2.5-flash-lite'
            $content = str_replace("'gemini-2.5-flash'", "'gemini-2.5-flash-lite'", $content);
            // Replace 'gemini-2.5-pro' with 'gemini-2.5-flash-lite'
            $content = str_replace("'gemini-2.5-pro'", "'gemini-2.5-flash-lite'", $content);

            file_put_contents($file->getPathname(), $content);
        }
    }
}

replaceInDirectory(__DIR__.'/tests');
replaceInDirectory(__DIR__.'/database');
replaceInDirectory(__DIR__.'/tests/Unit');
replaceInDirectory(__DIR__.'/tests/Feature');
