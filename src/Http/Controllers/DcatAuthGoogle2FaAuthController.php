<?php

namespace Asundust\DcatAuthGoogle2Fa\Http\Controllers;

use Asundust\DcatAuthGoogle2Fa\DcatAuthGoogle2FaServiceProvider;
use Asundust\DcatAuthGoogle2Fa\Models\AdminUser;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Http\Controllers\AuthController as BaseAuthController;
use Dcat\Admin\Http\Repositories\Administrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FAQRCode\Exceptions\MissingQrCodeServiceException;
use Symfony\Component\HttpFoundation\Response;

class DcatAuthGoogle2FaAuthController extends BaseAuthController
{
    /**
     * DcatAuthGoogle2FaAuthController constructor.
     */
    public function __construct()
    {
        $this->view = DcatAuthGoogle2FaServiceProvider::instance()->getName() . '::login';
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse|RedirectResponse|Response
     * @throws IncompatibleWithGoogleAuthenticatorException
     * @throws InvalidCharactersException
     * @throws SecretKeyTooShortException
     */
    public function postLogin(Request $request)
    {
        $credentials = $request->only([$this->username(), 'password']);
        $remember = (bool)$request->input('remember', false);

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($credentials, [
            $this->username() => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorsResponse($validator);
        }

        $validatorCode = Validator::make($request->only(['google_2fa_code']), [
            'google_2fa_code' => 'nullable|numeric|digits:6',
        ], [], [
            'google_2fa_code' => DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.2fa_code'),
        ]);

        if ($validatorCode->fails()) {
            return $this->validationErrorsResponse($validatorCode);
        }

        if ($this->guard()->attempt($credentials, $remember)) {
            /* @var AdminUser $user */
            $user = Admin::user();

            if ($user->status != AdminUser::STATUS_TRUE) {
                $this->guard()->logout();
                return $this->response()
                    ->error(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.login_status_false'))
                    ->send();
            }

            if ($user->google_two_fa_enable == AdminUser::GOOGLE_TWO_FA_ENABLE_TRUE) {
                $google2faCode = $request->input('google_2fa_code');
                if (!$google2faCode) {
                    $this->guard()->logout();
                    return $this
                        ->response()
                        ->withValidation(new MessageBag(['google_2fa_code' => [DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.login_need_code')]]))
                        ->send();
                }
                if (!(new Google2FA())->verifyKey($user->google_two_fa_secret, $google2faCode)) {
                    $this->guard()->logout();
                    return $this
                        ->response()
                        ->withValidation(new MessageBag(['google_2fa_code' => [DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.login_code_error')]]))
                        ->send();
                }
            }

            return $this->sendLoginResponse($request);
        }

        return $this->validationErrorsResponse([
            $this->username() => $this->getFailedLoginMessage(),
        ]);
    }

    /**
     * @return Form
     */
    protected function settingForm()
    {
        return new Form(new Administrator(), function (Form $form) {
            $form->action(admin_url('auth/setting'));

            $form->disableCreatingCheck();
            $form->disableEditingCheck();
            $form->disableViewCheck();

            $form->tools(function (Form\Tools $tools) {
                $tools->disableView();
                $tools->disableDelete();
            });

            $form->display('username', trans('admin.username'));
            $form->text('name', trans('admin.name'))->required();
            $form->image('avatar', trans('admin.avatar'))->autoUpload();

            $form->password('old_password', trans('admin.old_password'));

            $form->password('password', trans('admin.password'))
                ->minLength(5)
                ->maxLength(20)
                ->customFormat(function ($v) {
                    if ($v == $this['password']) {
                        return '';
                    }

                    return $v;
                });
            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password');

            /* @var AdminUser $user */
            $user = Admin::user();
            if ($user->google_two_fa_enable) {
                $form->switch('google_two_fa_enable', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa'))
                    ->help(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa_code_disable_button'));
                $form->text('google_2fa_code', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa_code'))
                    ->help(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa_code_disable_help'));
                $form->hidden('google_two_fa_secret');
                $form->hidden('current_google_two_fa_enable')->value('enable');
            } else {
                $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA();
                if (!$google2fa->getQrCodeService()) {
                    throw new MissingQrCodeServiceException(
                        DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.qrcode_service_tips')
                    );
                }
                $googleTwoFaSecret = $google2fa->generateSecretKey(32);
                $url = $google2fa->getQRCodeInline(config('admin.name'), $user->username, $googleTwoFaSecret);
                $form->display('google_2fa_qrcode', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa_qrcode'))
                    ->help(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa_qrcode_help'))
                    ->with(function () use ($url) {
                        return $url;
                    });
                $form->text('google_2fa_code', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa_code'))
                    ->help(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa_code_enable_help'));
                $form->hidden('google_two_fa_secret')->value($googleTwoFaSecret);
                $form->hidden('google_two_fa_enable');
                $form->hidden('current_google_two_fa_enable')->value('disable');
            }

            $form->ignore(['password_confirmation', 'old_password']);

            $form->saving(function (Form $form) {
                /* @var Form|AdminUser $form */
                /* @var AdminUser $adminUser */
                $adminUser = $form->model();
                if ($form->password && $adminUser->password != $form->password) {
                    $form->password = bcrypt($form->password);
                }

                if (!$form->password) {
                    $form->deleteInput('password');
                }

                if ($form->input('current_google_two_fa_enable') == 'enable') {
                    if (!$form->google_two_fa_enable) {
                        $google2faCode = $form->input('google_2fa_code');
                        if (!$google2faCode) {
                            return $form->response()->error(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.check_code_error'));
                        }
                        $google2fa = new Google2FA();
                        if (!$google2fa->verifyKey($adminUser->google_two_fa_secret, $google2faCode)) {
                            return $form->response()->error(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.check_code_error'));
                        }
                        $form->google_two_fa_enable = AdminUser::GOOGLE_TWO_FA_ENABLE_FALSE;
                        $form->google_two_fa_secret = null;
                    }
                } else {
                    $googleTwoFaSecret = $form->google_two_fa_secret;
                    $google2faCode = $form->input('google_2fa_code');
                    if ($googleTwoFaSecret) {
                        if ($google2faCode) {
                            $google2fa = new Google2FA();
                            if (!$google2fa->verifyKey($form->google_two_fa_secret, $google2faCode)) {
                                return $form->response()->error(DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.check_code_error'));
                            }
                            $form->google_two_fa_enable = AdminUser::GOOGLE_TWO_FA_ENABLE_TRUE;
                        } else {
                            $form->google_two_fa_secret = null;
                        }
                    }
                }
                $form->deleteInput(['google_2fa_code', 'current_google_two_fa_enable']);
            });

            $form->saved(function (Form $form) {
                return $form
                    ->response()
                    ->success(trans('admin.update_succeeded'))
                    ->redirect('auth/setting');
            });
        });
    }
}
