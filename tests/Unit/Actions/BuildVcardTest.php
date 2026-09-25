<?php

use App\Actions\LinkPage\BuildVcard;
use App\Data\LinkPage\ContactCard;

it('writes a business contact with every field and CRLF endings', function () {
    $vcard = (new BuildVcard)(new ContactCard(
        name: 'Taylor Drayson',
        givenName: 'Taylor',
        familyName: 'Drayson',
        organisation: 'The Creative Tinker',
        title: 'Web Designer',
        phone: '+447700900000',
        email: 'hello@example.com',
        website: 'https://thecreativetinker.com',
        profiles: ['LinkedIn' => 'https://www.linkedin.com/in/example'],
    ));

    expect(explode("\r\n", $vcard))->toBe([
        'BEGIN:VCARD',
        'VERSION:3.0',
        'N:Drayson;Taylor;;;',
        'FN:Taylor Drayson',
        'ORG:The Creative Tinker',
        'TITLE:Web Designer',
        'TEL;TYPE=CELL,VOICE:+447700900000',
        'EMAIL;TYPE=INTERNET:hello@example.com',
        'URL:https://thecreativetinker.com',
        'item1.URL:https://www.linkedin.com/in/example',
        'item1.X-ABLabel:LinkedIn',
        'END:VCARD',
        '',
    ])->and(substr_count($vcard, "\n"))->toBe(substr_count($vcard, "\r\n"));
});

it('leaves out the organisation, title and anything unset on a personal card', function () {
    $vcard = (new BuildVcard)(new ContactCard(name: 'Taylor Drayson', givenName: 'Taylor', familyName: 'Drayson'));

    expect($vcard)->not->toContain('ORG:')
        ->not->toContain('TITLE:')
        ->not->toContain('TEL')
        ->not->toContain('EMAIL')
        ->not->toContain('PHOTO');
});

it('escapes the characters vCard text reserves', function () {
    $vcard = (new BuildVcard)(new ContactCard(
        name: 'Taylor, Esq.',
        givenName: 'Tay;lor',
        familyName: 'Dray\son',
        organisation: "Tinker; Co\nLtd",
    ));

    expect($vcard)->toContain('N:Dray\\\\son;Tay\;lor;;;')
        ->toContain('FN:Taylor\\, Esq.')
        ->toContain('ORG:Tinker\; Co\\nLtd');
});

it('embeds the photo as base64, folded to 75 octets per line', function () {
    $photo = tempnam(sys_get_temp_dir(), 'vcard');
    file_put_contents($photo, random_bytes(600));

    $vcard = (new BuildVcard)(new ContactCard(name: 'T D', givenName: 'T', familyName: 'D', photoPath: $photo));
    $lines = explode("\r\n", $vcard);
    $unfolded = str_replace("\r\n ", '', $vcard);

    expect($unfolded)->toContain('PHOTO;ENCODING=b;TYPE=JPEG:'.base64_encode((string) file_get_contents($photo)))
        ->and(max(array_map('strlen', $lines)))->toBeLessThanOrEqual(75);

    unlink($photo);
});
