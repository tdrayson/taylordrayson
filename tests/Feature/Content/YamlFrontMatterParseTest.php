<?php

use App\Content\EntryFileRepository;

it('parses frontmatter without treating markdown code-fence dashes as the closer', function () {
    $contents = <<<'MD'
---
id: 01JTESTFRONTMATTER000000000
type: note
slug: fence-safe
---

Before the fence.

```yaml
---
title: not frontmatter
---
```

After the fence.
MD;

    $parsed = app(EntryFileRepository::class)->parse($contents);

    expect($parsed['frontmatter']['id'])->toBe('01JTESTFRONTMATTER000000000')
        ->and($parsed['frontmatter']['slug'])->toBe('fence-safe')
        ->and($parsed['body'])->toContain('Before the fence.')
        ->and($parsed['body'])->toContain('title: not frontmatter')
        ->and($parsed['body'])->toContain('After the fence.');
});
