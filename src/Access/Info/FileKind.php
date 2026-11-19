<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Info;

enum FileKind: string
{
    case directory = 'directory';
    case page = 'page';
    case asset = 'asset';
    case data = 'data';
    case template = 'template';
    case other = 'other';
}
