<?php

namespace App\Enums;

enum MediaType: string
{
    case Film = 'film';
    case TvEpisode = 'episode';
    case Book = 'book';

    public function label(): string
    {
        return match ($this) {
            self::Film => 'Film',
            self::TvEpisode => 'TV episode',
            self::Book => 'Book',
        };
    }
}
