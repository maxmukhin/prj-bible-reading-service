<?php
// src/Domain/ValueObject/TargetType.php

namespace App\Domain\ValueObject;

enum TargetType: string
{
    case BOOK = 'book';
    case CHAPTER = 'chapter';
    case VERSE = 'verse';
}

