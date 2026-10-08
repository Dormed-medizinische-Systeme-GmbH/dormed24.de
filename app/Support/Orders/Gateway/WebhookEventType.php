<?php

namespace App\Support\Orders\Gateway;

enum WebhookEventType
{
    case PaymentApproved;
    case CaptureCompleted;
    case CapturePending;
    case CaptureDenied;
    case CaptureRefunded;
    case CaptureReversed;
    case Unhandled;
}
