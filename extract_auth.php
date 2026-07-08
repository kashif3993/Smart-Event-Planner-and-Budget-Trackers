<?php

function extractAssets($bladePath, $cssPath, $jsPath, $cssAssetPath, $jsAssetPath) {
    if (!file_exists($bladePath)) {
        echo "File not found: $bladePath\n";
        return;
    }
    
    $content = file_get_contents($bladePath);
    
    // Extract CSS
    if (preg_match('/<style>(.*?)<\/style>/s', $content, $cssMatches)) {
        file_put_contents($cssPath, trim($cssMatches[1]));
        $content = preg_replace('/<style>.*?<\/style>/s', '<link rel="stylesheet" href="{{ asset(\'' . $cssAssetPath . '\') }}">', $content);
        echo "Extracted CSS for $bladePath\n";
    }
    
    // Extract JS
    if (preg_match('/<script>(.*?)<\/script>/s', $content, $jsMatches)) {
        file_put_contents($jsPath, trim($jsMatches[1]));
        $content = preg_replace('/<script>.*?<\/script>/s', '<script src="{{ asset(\'' . $jsAssetPath . '\') }}" defer></script>', $content);
        echo "Extracted JS for $bladePath\n";
    }
    
    file_put_contents($bladePath, $content);
}

// Login
extractAssets(
    'resources/views/auth/login.blade.php',
    'public/css/login.css',
    'public/js/login.js',
    'css/login.css',
    'js/login.js'
);

// Register
extractAssets(
    'resources/views/auth/register.blade.php',
    'public/css/register.css',
    'public/js/register.js',
    'css/register.css',
    'js/register.js'
);

echo "All extraction complete.\n";
