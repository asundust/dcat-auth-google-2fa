<?php

namespace Asundust\DcatAuthGoogle2Fa\Http\Controllers\Actions;

use Asundust\DcatAuthGoogle2Fa\DcatAuthGoogle2FaServiceProvider;
use Asundust\DcatAuthGoogle2Fa\Models\AdminUser;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;

class UnbindRowAction extends RowAction
{
    /**
     * UnbindRowAction constructor.
     */
    public function __construct()
    {
        parent::__construct(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.2fa_unbind'));
    }

    /**
     * @return Response
     */
    public function handle()
    {
        AdminUser::query()->where('id', $this->getKey())->update(['google_two_fa_secret' => null, 'google_two_fa_enable' => AdminUser::GOOGLE_TWO_FA_ENABLE_FALSE]);
        return $this->response()->success(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.2fa_unbind_success'))->refresh();
    }


    /**
     * @return string[]
     */
    public function confirm()
    {
        return [
            DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.message'),
            DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.2fa_unbind_confirm'),
        ];
    }
}
