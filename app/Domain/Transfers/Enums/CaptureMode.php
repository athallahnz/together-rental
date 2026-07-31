<?php

namespace App\Domain\Transfers\Enums;

enum CaptureMode: string
{
    case CameraRequired = 'camera_required';
    case CameraPreferred = 'camera_preferred';
    case GalleryAllowed = 'gallery_allowed';
}
