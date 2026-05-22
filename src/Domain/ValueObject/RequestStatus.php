<?php
// src/Domain/ValueObject/RequestStatus.php

namespace App\Domain\ValueObject;

enum RequestStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
}

