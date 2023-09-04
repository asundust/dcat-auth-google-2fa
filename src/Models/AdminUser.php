<?php

namespace Asundust\DcatAuthGoogle2Fa\Models;

use Dcat\Admin\Models\Administrator;

/**
 * @property string $username
 * @property string $password
 * @property string $google_two_fa_secret
 * @property int $google_two_fa_enable
 * @property int $status
 */
class AdminUser extends Administrator
{
    public const STATUS_FALSE = 0;
    public const STATUS_TRUE = 1;

    public const GOOGLE_TWO_FA_ENABLE_FALSE = 0;
    public const GOOGLE_TWO_FA_ENABLE_TRUE = 1;
}
