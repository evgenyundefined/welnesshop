<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingGateway = 'awaiting_gateway';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
