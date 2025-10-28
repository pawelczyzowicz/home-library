<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Book;

enum BookSource: string
{
    case MANUAL = 'manual';
    case AI_RECOMMENDATION = 'ai_recommendation';
}
