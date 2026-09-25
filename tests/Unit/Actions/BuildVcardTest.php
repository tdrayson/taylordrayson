<?php

use App\Actions\LinkPage\BuildVcard;
use App\Data\LinkPage\ContactCard;

it('writes a business contact with work labels and CRLF endings', function () {
    $vcard = (new BuildVcard)(new ContactCard(
        name: 'Taylor Drayson',
        givenName: 'Taylor',
        familyName: 'Drayson',
        organisation: 'The Creative Tinker',
        title: 'Web Developer',
        phone: '+447700900000',
        emails: ['work' => 'hello@example.com'],
        websites: ['work' => 'https://thecreativetinker.com'],
        work: true,
        profiles: ['LinkedIn' => 'https://www.linkedin.com/in/example', 'WhatsApp' => 'https://wa.me/447700900000'],
    ));

    expect(explode("\r\n", $vcard))->toBe([
        'BEGIN:VCARD',
        'VERSION:3.0',
        'N:Drayson;Taylor;;;',
        'FN:Taylor Drayson',
        'ORG:The Creative Tinker',
        'TITLE:Web Developer',
        'TEL;TYPE=WORK,VOICE;waid=447700900000:+447700900000',
        'EMAIL;TYPE=INTERNET,WORK:hello@example.com',
        'URL;TYPE=WORK:https://thecreativetinker.com',
        'item1.URL:https://www.linkedin.com/in/example',
        'item1.X-ABLabel:LinkedIn',
        'item2.URL:https://wa.me/447700900000',
        'item2.X-ABLabel:WhatsApp',
        'END:VCARD',
        '',
    ])->and(substr_count($vcard, "\n"))->toBe(substr_count($vcard, "\r\n"));
});

it('gives a personal contact a mobile, both inboxes and sites, and a birthday', function () {
    $vcard = (new BuildVcard)(new ContactCard(
        name: 'Taylor Drayson',
        givenName: 'Taylor',
        familyName: 'Drayson',
        phone: '+447700900000',
        emails: ['home' => 'me@example.com', 'work' => 'hello@example.com'],
        websites: ['home' => 'https://taylordrayson.com', 'work' => 'https://thecreativetinker.com'],
        birthday: '1990-01-31',
    ));

    expect($vcard)->toContain("TEL;TYPE=CELL,VOICE;waid=447700900000:+447700900000\r\n")
        ->toContain("EMAIL;TYPE=INTERNET,HOME:me@example.com\r\nEMAIL;TYPE=INTERNET,WORK:hello@example.com\r\n")
        ->toContain("URL;TYPE=HOME:https://taylordrayson.com\r\nURL;TYPE=WORK:https://thecreativetinker.com\r\n")
        ->toContain("BDAY:1990-01-31\r\n")
        ->and(substr_count($vcard, 'TEL;'))->toBe(1);
});

it('leaves out anything unset', function () {
    $vcard = (new BuildVcard)(new ContactCard(name: 'Taylor Drayson', givenName: 'Taylor', familyName: 'Drayson'));

    expect($vcard)->not->toContain('ORG:')
        ->not->toContain('TITLE:')
        ->not->toContain('TEL')
        ->not->toContain('EMAIL')
        ->not->toContain('BDAY')
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
