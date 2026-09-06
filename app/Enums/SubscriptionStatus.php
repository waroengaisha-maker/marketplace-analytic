<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case None = 'none';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case Canceled = 'canceled';
}
