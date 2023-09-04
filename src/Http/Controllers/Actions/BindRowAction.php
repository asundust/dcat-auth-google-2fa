<?php

namespace Asundust\DcatAuthGoogle2Fa\Http\Controllers\Actions;

use Asundust\DcatAuthGoogle2Fa\DcatAuthGoogle2FaServiceProvider;
use Asundust\DcatAuthGoogle2Fa\Models\AdminUser;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;
use PragmaRX\Google2FA\Google2FA;

class BindRowAction extends RowAction
{
    /**
     * BindRowAction constructor.
     */
    public function __construct()
    {
        parent::__construct(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.2fa_bind'));
    }

    /**
     * @return Response
     * @throws IncompatibleWithGoogleAuthenticatorException
     * @throws InvalidCharactersException
     * @throws SecretKeyTooShortException
     */
    public function handle()
    {
        $google2Fa = new Google2FA;
        $secret = $google2Fa->generateSecretKey(32);
        /* @var AdminUser $user */
        $user = AdminUser::query()->find($this->getKey());
        $user->google_two_fa_secret = $secret;
        $user->google_two_fa_enable = AdminUser::GOOGLE_TWO_FA_ENABLE_TRUE;
        $user->save();
        return $this->response()->success(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.2fa_bind_success'))->refresh();
    }

    /**
     * @return string[]
     */
    public function confirm()
    {
        return [
            DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.message'),
            DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.2fa_bind_confirm'),
        ];
    }
}
