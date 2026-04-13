<?php

namespace App\Enums;

enum EventStatus: string
{
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case Past = 'past';
}
