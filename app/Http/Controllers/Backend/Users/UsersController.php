<?php

namespace App\Http\Controllers\Backend\Users;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\Activity\SaveActivityLogController;
use App\Models\History;
use App\Models\Role;
use App\Models\User;
use Auth;
use Config;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class UsersController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     * More info DataTables : https://yajrabox.com/docs/laravel-datatables/master
     *
     * @param Datatables $datatables
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function index(Datatables $datatables)
    {
        $columns = [
            'id' => ['title' => 'No.', 'orderable' => false, 'searchable' => false, 'render' => function () {
                return 'function(data,type,fullData,meta){return meta.settings._iDisplayStart+meta.row+1;}';
            }],
            'image',
            'name',
            'email',
            'role',
            'created_at',
            'updated_at',
            'action' => ['orderable' => false, 'searchable' => false],
        ];

        if ($datatables->getRequest()->ajax()) {
            return $datatables->of(User::all())
                ->addColumn('image', function (User $data) {
                    $getAssetFolder = asset('uploads/' . $data->image);
                    return '<img src="' . $getAssetFolder . '" width="30px" class="img-circle elevation-2">';
                })
                ->addColumn('action', function (User $data) {
                    $routeEdit = route($this->getRoute() . '.edit', $data->id);
                    $routeDelete = route($this->getRoute() . '.delete', $data->id);

                    // Check is administrator
                    if (Auth::user()->hasRole('administrator')) {
                        $button = '<a href="' . $routeEdit . '"><button class="btn btn-primary"><i class="fa fa-edit"></i></button></a> ';
                        $button .= '<a href="' . $routeDelete . '" class="delete-button"><button class="btn btn-danger"><i class="fa fa-trash"></i></button></a>';
                    } else {
                        $button = '<a href="#"><button class="btn btn-primary disabled"><i class="fa fa-edit"></i></button></a> ';
                        $button .= '<a href="#"><button class="btn btn-danger disabled"><i class="fa fa-trash"></i></button></a>';
                    }
                    return $button;
                })
                ->addColumn('role', function (User $user) {
                    return Role::where('id', $user->role)->first()->display_name;
                })
                ->rawColumns(['action', 'image', 'intro'])
                ->toJson();
        }

        $html = $datatables->getHtmlBuilder()
            ->columns($columns)
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
                'lengthMenu' => [
                    [10, 25, 50, -1],
                    ['10 rows', '25 rows', '50 rows', 'Show all'],
                ],
                'dom' => 'Bfrtip',
                'buttons' => ['pageLength', 'csv', 'excel', 'pdf', 'print'],
            ]);

        return view('backend.users.index', compact('html'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function add()
    {

        $data = new User();
        $data->form_action = $this->getRoute() . '.create';
        // Add page type here to indicate that the form.blade.php is in 'add' mode
        $data->page_type = 'add';
        $data->button_text = 'Add';

        if (Auth::user()->hasRole('administrator')) {
            return view('backend.users.form', [
                'data' => $data,
                'role' => Role::orderBy('id')->pluck('display_name', 'id'),
            ]);
        }

        return view('backend.users.form', [
            'data' => $data,
            'role' => Role::whereNotIn('id', [1, 2])->orderBy('id')->pluck('display_name', 'id'),
        ]);
    }

    /**
     * Get named route depends on which user is logged in
     *
     * @return String
     */
    private function getRoute()
    {
        return 'users';
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $new = $request->all();
        $this->validator($new, 'create')->validate();
        try {
            $new['password'] = bcrypt($new['password']);
            $createNew = User::create($new);
            if ($createNew) {
                // Attach role
                $createNew->roles()->attach($new['role']);

                // upload image
                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    // image file name example: [news_id]_image.jpg
                    ${'image'} = $createNew->id . "_image." . $file->getClientOriginalExtension();
                    // save image to the path
                    $file->move(Config::get('const.UPLOAD_PATH'), ${'image'});
                    $createNew->{'image'} = ${'image'};
                } else {
                    $createNew->{'image'} = 'default-user.png';
                }

                // If user create with 2 or 3 means admin or staff, will save the QR history also
                if ($new['role'] == 2 || $new['role'] == 3) {
                    History::create($new);

                    // Save log
                    $controller = new SaveActivityLogController();
                    $controller->saveLog($new, "Create new history QR");
                }

                // Save user
                $createNew->save();

                // Save log
                $controller = new SaveActivityLogController();
                $controller->saveLog($new, "Create new user");

                // Create is successful, back to list
                return redirect()->route($this->getRoute())->with('success', Config::get('const.SUCCESS_CREATE_MESSAGE'));
            }

            // Create is failed
            return redirect()->route($this->getRoute())->with('error', Config::get('const.FAILED_CREATE_MESSAGE'));
        } catch (Exception $e) {
            // Create is failed
            return redirect()->route($this->getRoute())->with('error', Config::get('const.FAILED_CREATE_MESSAGE'));
        }
    }

    /**
     * Validator data.
     *
     * @param array $data
     * @param $type
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data, $type)
    {
        // Determine if password validation is required depending on the calling
        return Validator::make($data, [
            // Add unique validation to prevent for duplicate email while forcing unique rule to ignore a given ID
            'email' => $type == 'create' ? 'email|required|string|max:255|unique:users' : 'required|string|max:255|unique:users,email,' . $data['id'],
            // (update: not required, create: required)
            'password' => $type == 'create' ? 'required|string|min:6|max:255' : '',
            'name' => $this->validName($type, $data),
        ]);
    }

    /**
     * Validator data for name, the name must be uniq for the QR Code.
     *
     * @param array $data
     * @param $type
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validName($type, $data)
    {
        // If type create
        if ($type == 'create') {
            return 'required|string|max:255|unique:histories,name';
        } else {
            // If type edit
            if ($data['old_role'] == 1 || $data['old_role'] == 4) {
                return 'required|string|max:255';
            } else {
                return 'required|string|max:255|unique:histories,name,' . $data['qr_id'];
            }
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data = User::find($id);
        $data->form_action = $this->getRoute() . '.update';
        // Add page type here to indicate that the form.blade.php is in 'edit' mode
        $data->page_type = 'edit';
        $data->button_text = 'Edit';

        // Get history id by name
        $getHistory = History::where('name', $data->name)
            ->first();
        $getHistory ? $data->qr_id = $getHistory->id : 0;

        if (Auth::user()->hasRole('administrator')) {
            return view('backend.users.form', [
                'data' => $data,
                'role' => Role::orderBy('id')->pluck('display_name', 'id'),
            ]);
        }

        return view('backend.users.form', [
            'data' => $data,
            'role' => Role::whereNotIn('id', [1, 2])->orderBy('id')->pluck('display_name', 'id'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $new = $request->all();
        try {
            $currentData = User::find($request->get('id'));
            $new['old_role'] = $currentData->role;

            if ($currentData) {
                $this->validator($new, 'update')->validate();

                if (!$new['password']) {
                    $new['password'] = $currentData['password'];
                } else {
                    $new['password'] = bcrypt($new['password']);
                }

                if ($currentData->role != $new['role']) {
                    $currentData->roles()->sync($new['role']);
                }

                // check delete flag: [name ex: image_delete]
                if ($request->get('image_delete') != null) {
                    $new['image'] = null; // filename for db

                    if ($currentData->{'image'} != 'default-user.png') {
                        @unlink(Config::get('const.UPLOAD_PATH') . $currentData['image']);
                    }
                }

                // if new image is being uploaded
                // upload image
                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    // image file name example: [id]_image.jpg
                    ${'image'} = $currentData->id . "_image." . $file->getClientOriginalExtension();
                    $new['image'] = ${'image'};
                    // save image to the path
                    $file->move(Config::get('const.UPLOAD_PATH'), ${'image'});
                } else {
                    $new['image'] = 'default-user.png';
                }

                // If user update role 2 or 3 means admin or staff will upadate also the history QR
                if ($request->has('qr_id') && ($new['role'] == 2 || $new['role'] == 3)) {
                    $currentDataHistory = History::find($request->get('qr_id'));

                    if ($currentDataHistory) {
                        // Update
                        $currentDataHistory->update($new);

                        // Save log
                        $controller = new SaveActivityLogController();
                        $controller->saveLog($new, "Update history QR");
                    }
                }

                // Update
                $currentData->update($new);

                // Save log
                $controller = new SaveActivityLogController();
                $controller->saveLog($new, "Update user");

                return redirect()->route($this->getRoute())->with('success', Config::get('const.SUCCESS_UPDATE_MESSAGE'));
            }

            // If update is failed
            return redirect()->route($this->getRoute())->with('error', Config::get('const.FAILED_UPDATE_MESSAGE'));
        } catch (Exception $e) {
            // If update is failed
            return redirect()->route($this->getRoute())->with('error', Config::get('const.FAILED_CREATE_MESSAGE'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        try {
            if (Auth::user()->id != $id) {

                // delete
                $user = User::find($id);
                $user->detachRole($id);

                // Delete the image
                if ($user->{'image'} != 'default-user.png') {
                    @unlink(Config::get('const.UPLOAD_PATH') . $user['image']);
                }

                // Delete the data DB
                $user->delete();

                // Save log
                $controller = new SaveActivityLogController();
                $controller->saveLog($user->toArray(), "Delete user");

                //delete success
                return redirect()->route($this->getRoute())->with('success', Config::get('const.SUCCESS_DELETE_MESSAGE'));
            }
            // delete failed
            return redirect()->route($this->getRoute())->with('error', Config::get('const.FAILED_DELETE_SELF_MESSAGE'));
        } catch (Exception $e) {
            // delete failed
            return redirect()->route($this->getRoute())->with('error', Config::get('const.FAILED_DELETE_MESSAGE'));
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route($this->getRoute())->with('error', Config::get('const.ERROR_FOREIGN_KEY'));
        }
    }
}
