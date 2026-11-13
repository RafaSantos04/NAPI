<?php

use Symfony\Component\Finder\Finder;

// P2.5-T08: GitHub's secret scanning flagged a password literal in a test
// ('secret-password'). Test credentials come from UserFactory::password() or
// Str::password(), and demo seeder credentials come from configuration. A
// literal that looks like a credential fails here, before it reaches a push.
//
// Still allowed: values that are the subject of a validation, shorter than
// 6 characters (the smallest password any rule accepts), and messages,
// which contain spaces.

/**
 * @return list<string> "file:line" of each credential literal found
 */
function credentialLiterals(): array
{
    $root = dirname(__DIR__, 2);
    $patterns = [
        // The password or password_confirmation key mapped to a literal
        "/'password(?:_confirmation)?'\s*=>\s*'[^'\s]{6,}'/",
        // A login helper's password argument: helper($email, 'literal')
        "/(?:attempt(?:Api|Admin)Login|adminLogin|confirmAdminPassword)\(\s*[^,()]+,\s*'[^'\s]{6,}'/",
        // A hashed literal, as the factory used to have
        "/Hash::make\(\s*'[^']+'\s*\)/",
    ];

    $files = Finder::create()->files()->name('*.php')->in([
        $root.'/tests',
        $root.'/database/factories',
        $root.'/database/seeders',
    ]);
    $found = [];

    foreach ($files as $file) {
        foreach (file($file->getRealPath()) as $index => $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $found[] = str_replace('\\', '/', $file->getRelativePathname()).':'.($index + 1);
                }
            }
        }
    }

    return $found;
}

it('keeps credential literals out of tests, factories and seeders', function () {
    expect(credentialLiterals())->toBe([]);
});
