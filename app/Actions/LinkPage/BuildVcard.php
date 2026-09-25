<?php

namespace App\Actions\LinkPage;

use App\Data\LinkPage\ContactCard;

/**
 * Renders a contact as a vCard 3.0 (RFC 2426): escaped text values, folded lines, CRLF endings.
 */
final class BuildVcard
{
    private const EOL = "\r\n";

    private const FOLD_OCTETS = 75;

    public function __invoke(ContactCard $card): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:'.$this->escape($card->familyName).';'.$this->escape($card->givenName).';;;',
            'FN:'.$this->escape($card->name),
            ...$this->optional('ORG', $card->organisation),
            ...$this->optional('TITLE', $card->title),
            ...$this->phone($card),
            ...$this->typed('EMAIL;TYPE=INTERNET,', $card->emails),
            ...$this->typed('URL;TYPE=', $card->websites),
            ...($card->birthday === null ? [] : ['BDAY:'.$card->birthday]),
            ...$this->profiles($card->profiles),
            ...($card->photoPath === null ? [] : ['PHOTO;ENCODING=b;TYPE=JPEG:'.base64_encode((string) file_get_contents($card->photoPath))]),
            'END:VCARD',
        ];

        return implode(self::EOL, array_map($this->fold(...), $lines)).self::EOL;
    }

    /**
     * A mobile, or a work number. `waid` is WhatsApp's own parameter, so the contact opens straight into a chat there.
     *
     * @return list<string>
     */
    private function phone(ContactCard $card): array
    {
        if ($card->phone === null) {
            return [];
        }

        $type = $card->work ? 'WORK,VOICE' : 'CELL,VOICE';

        return ["TEL;TYPE={$type};waid=".ltrim($card->phone, '+').':'.$card->phone];
    }

    /**
     * One line per value, typed HOME or WORK from its key.
     *
     * @param  array<'home'|'work', string>  $values
     * @return list<string>
     */
    private function typed(string $prefix, array $values): array
    {
        return array_map(
            fn (string $type, string $value): string => $prefix.strtoupper($type).':'.$value,
            array_keys($values),
            array_values($values),
        );
    }

    /**
     * @return list<string>
     */
    private function optional(string $property, ?string $value): array
    {
        return blank($value) ? [] : [$property.':'.$this->escape($value)];
    }

    /**
     * Labelled URLs, the grouping Apple and Google Contacts both show with their label.
     *
     * @param  array<string, string>  $profiles
     * @return list<string>
     */
    private function profiles(array $profiles): array
    {
        $lines = [];
        $item = 0;

        foreach ($profiles as $label => $url) {
            $item++;
            $lines[] = "item{$item}.URL:{$url}";
            $lines[] = "item{$item}.X-ABLabel:".$this->escape($label);
        }

        return $lines;
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value,
        );
    }

    /** Split a line into 75-octet chunks, each continuation led by a space, never inside a UTF-8 character. */
    private function fold(string $line): string
    {
        $chunks = [];
        $width = self::FOLD_OCTETS;

        while (strlen($line) > $width) {
            $chunk = mb_strcut($line, 0, $width, 'UTF-8');
            $chunks[] = $chunk;
            $line = substr($line, strlen($chunk));
            // A continuation's leading space counts towards its 75 octets.
            $width = self::FOLD_OCTETS - 1;
        }

        $chunks[] = $line;

        return implode(self::EOL.' ', $chunks);
    }
}
