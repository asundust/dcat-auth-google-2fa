<?php

namespace Asundust\DcatAuthGoogle2Fa\Http\Controllers;

use Asundust\DcatAuthGoogle2Fa\DcatAuthGoogle2FaServiceProvider;
use Asundust\DcatAuthGoogle2Fa\Http\Controllers\Actions\BindRowAction;
use Asundust\DcatAuthGoogle2Fa\Http\Controllers\Actions\UnbindRowAction;
use Asundust\DcatAuthGoogle2Fa\Models\AdminUser;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\UserController;
use Dcat\Admin\Http\Repositories\Administrator;
use Dcat\Admin\Show;
use Dcat\Admin\Widgets\Tree;
use PragmaRX\Google2FA\Google2FA;

class DcatAuthGoogle2FaUserController extends UserController
{
    protected function grid()
    {
        return Grid::make(Administrator::with(['roles']), function (Grid $grid) {
            $grid->column('id', trans('id'))->sortable();
            $grid->column('username', trans('admin.username'));
            $grid->column('name', trans('admin.name'));

            $grid->column('status', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.status'))
                ->bool();
            $grid->column('google_two_fa_enable', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa'))
                ->bool()
                ->if(function () {
                    /* @var AdminUser $this */
                    return $this->google_two_fa_enable;
                })
                ->qrcode(function () {
                    /* @var AdminUser $this */
                    $google2fa = new Google2FA();
                    return $google2fa->getQRCodeUrl(config('admin.name'), $this->username, $this->google_two_fa_secret);
                }, 200, 200);

            if (config('admin.permission.enable')) {
                $grid->column('roles', trans('admin.roles'))->pluck('name')->label('primary', 3);

                $permissionModel = config('admin.database.permissions_model');
                $roleModel = config('admin.database.roles_model');
                $nodes = (new $permissionModel())->allNodes();
                $grid->column('permissions', trans('admin.permission'))
                    ->if(function () {
                        return !$this['roles']->isEmpty();
                    })
                    ->showTreeInDialog(function (Grid\Displayers\DialogTree $tree) use (&$nodes, $roleModel) {
                        $tree->nodes($nodes);

                        foreach (array_column($this['roles']->toArray(), 'slug') as $slug) {
                            if ($roleModel::isAdministrator($slug)) {
                                $tree->checkAll();
                            }
                        }
                    })
                    ->else()
                    ->display('');
            }

            $grid->column('created_at', trans('admin.created_at'));
            $grid->column('updated_at', trans('admin.updated_at'))->sortable();

            $grid->quickSearch(['id', 'name', 'username']);

            $grid->showQuickEditButton();
            $grid->enableDialogCreate();
            $grid->showColumnSelector();
            $grid->disableEditButton();

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                if ($actions->getKey() == AdminUser::DEFAULT_ID) {
                    $actions->disableDelete();
                }
                if ($actions->row['google_two_fa_enable']) {
                    $actions->append(new UnbindRowAction());
                } else {
                    $actions->append(new BindRowAction());
                }
            });
        });
    }

    protected function detail($id)
    {
        return Show::make($id, Administrator::with(['roles']), function (Show $show) {
            $show->field('id', trans('id'));
            $show->field('username', trans('admin.username'));
            $show->field('name', trans('admin.name'));

            $show->field('status', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.status'))
                ->bool();
            $show->field('google_two_fa_enable', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa'))
                ->bool();

            $show->field('avatar', __('admin.avatar'))->image();

            if (config('admin.permission.enable')) {
                $show->field('roles', trans('admin.roles'))->as(function ($roles) {
                    if (!$roles) {
                        return [];
                    }

                    return collect($roles)->pluck('name');
                })->label();

                $show->field('permissions', trans('admin.permission'))->unescape()->as(function () {
                    $roles = $this['roles']->toArray();

                    $permissionModel = config('admin.database.permissions_model');
                    $roleModel = config('admin.database.roles_model');
                    $permissionModel = new $permissionModel();
                    $nodes = $permissionModel->allNodes();

                    $tree = Tree::make($nodes);

                    $isAdministrator = false;
                    foreach (array_column($roles, 'slug') as $slug) {
                        if ($roleModel::isAdministrator($slug)) {
                            $tree->checkAll();
                            $isAdministrator = true;
                        }
                    }

                    if (!$isAdministrator) {
                        $keyName = $permissionModel->getKeyName();
                        $tree->check(
                            $roleModel::getPermissionId(array_column($roles, $keyName))->flatten()
                        );
                    }

                    return $tree->render();
                });
            }

            $show->field('created_at', trans('admin.created_at'));
            $show->field('updated_at', trans('admin.updated_at'));
        });
    }

    public function form()
    {
        return Form::make(Administrator::with(['roles']), function (Form $form) {
            $userTable = config('admin.database.users_table');

            $connection = config('admin.database.connection');

            $id = $form->getKey();

            $form->display('id', 'ID');

            $form->text('username', trans('admin.username'))
                ->required()
                ->creationRules(['required', "unique:$connection.$userTable"])
                ->updateRules(['required', "unique:$connection.$userTable,username,$id"]);
            $form->text('name', trans('admin.name'))->required();
            $form->image('avatar', trans('admin.avatar'))->autoUpload();
            $form->switch('status', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.status'))->default(AdminUser::STATUS_TRUE);
            $form->switch('google_two_fa_enable', DcatAuthGoogle2FaServiceProvider::trans('dcat-auth-google-2fa.google_2fa'))->default(AdminUser::GOOGLE_TWO_FA_ENABLE_FALSE);

            if ($id) {
                $form->password('password', trans('admin.password'))
                    ->minLength(5)
                    ->maxLength(20)
                    ->customFormat(function () {
                        return '';
                    });
            } else {
                $form->password('password', trans('admin.password'))
                    ->required()
                    ->minLength(5)
                    ->maxLength(20);
            }

            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password');

            $form->ignore(['password_confirmation']);

            if (config('admin.permission.enable')) {
                $form->multipleSelect('roles', trans('admin.roles'))
                    ->options(function () {
                        $roleModel = config('admin.database.roles_model');

                        return $roleModel::all()->pluck('name', 'id');
                    })
                    ->customFormat(function ($v) {
                        return array_column($v, 'id');
                    });
            }

            $form->display('created_at', trans('admin.created_at'));
            $form->display('updated_at', trans('admin.updated_at'));

            if ($id == AdminUser::DEFAULT_ID) {
                $form->disableDeleteButton();
            }
        })->saving(function (Form $form) {
            /* @var Form|AdminUser $form */
            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }

            if (!$form->password) {
                $form->deleteInput('password');
            }

            if ($form->google_two_fa_enable == AdminUser::GOOGLE_TWO_FA_ENABLE_TRUE) {
                $form->google_two_fa_secret = (new Google2FA())->generateSecretKey(32);
            } else {
                $form->google_two_fa_secret = null;
            }
        });
    }
}
