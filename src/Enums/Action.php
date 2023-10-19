<?php

namespace LaravelVault\Enums;

enum Action
{
    case create;
    case read;
    case update;
    case delete;
    case list;
}
