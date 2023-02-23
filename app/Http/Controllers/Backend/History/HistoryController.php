<?php
namespace App\Http\Controllers\Backend\History;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Utils\Activity\SaveActivityLogController;
use Yajra\Datatables\Datatables;
use App\Models\History;
use Auth;
use Config;
use Crypt;

class HistoryController extends Controller
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
            'id' => ['title' => 'No.', 'orderable' => false, 'searchable' => false, 'render' => function() {
                return 'function(data,type,fullData,meta){return meta.settings._iDisplayStart+meta.row+1;}';
            }],
            'name',
            'download_qr_code',
            'created_at',
            'updated_at',
            'action' => ['orderable' => false, 'searchable' => false]
        ];

        if ($datatables->getRequest()->ajax()) {
            return $datatables->of(History::all())
                ->addColumn('action', function (History $data){
                    $routeEdit = route($this->getRoute() . ".edit", $data->id);
                    $routeDelete = route($this->getRoute() . ".delete", $data->id);

                    $button = '<div class="col-sm-12"><div class="row">';
                    $button .= '<div class="col-sm-4"><a href="'.$routeEdit.'"><button class="btn btn-primary"><i class="fa fa-edit"></i></button></a></div> ';
                    if (Auth::user()->hasRole('administrator')) { // Check the role
                        $button .= '<div class="col-sm-4"><a href="'.$routeDelete.'" class="delete-button"><button class="btn btn-danger"><i class="fa fa-trash"></i></button></a></div>';
                    } else {
                        $button .= '<div class="col-sm-4"><a href="#"><button class="btn btn-danger disabled"><i class="fa fa-trash"></i></button></a></div>';
                    }
                    $button .= '</div></div>';
                    return $button;
                })
                ->addColumn('download_qr_code', function (History $data){
                    $routeDownloadQr = "https://chart.googleapis.com/chart?chs=400x400&cht=qr&chl=".Crypt::encryptString($data->id)."&choe=UTF-8";
                    return '<a href="'.$routeDownloadQr.'" target="_blank"><button class="btn btn-outline-primary">Download</button></a>';
                })
                ->rawColumns(['action', 'download_qr_code'])
                ->toJson();
        }

        $columnsArrExPr = [1,3,4];
        $html = $datatables->getHtmlBuilder()
            ->columns($columns)
            ->parameters([
                'order' => [[1,'desc']],
                'responsive' => true,
                'autoWidth' => false,
                'lengthMenu' => [
                    [ 10, 25, 50, -1 ],
                    [ '10 rows', '25 rows', '50 rows', 'Show all' ]
                ],
                'dom' => 'Bfrtip',
                'buttons' => $this->buttonDatatables($columnsArrExPr),
            ]);

        return view('backend.histories.index', compact('html'));
    }

    /**
     * Fungtion show button for export or print.
     *
     * @param $columnsArrExPr
     * @return array[]
     */
    public function buttonDatatables($columnsArrExPr){
        return [
            [
                'pageLength'
            ],
            [
                'extend' => 'csvHtml5',
                'exportOptions' => [
                    'columns' => $columnsArrExPr
                ]
            ],
            [
                'extend' => 'pdfHtml5',
                'exportOptions' => [
                    'columns' => $columnsArrExPr
                ]
            ],
            [
                'extend' => 'excelHtml5',
                'exportOptions' => [
                    'columns' => $columnsArrExPr
                ]
            ],
            [
                'extend' => 'print',
                'exportOptions' => [
                    'columns' => $columnsArrExPr
                ]
            ],
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function add()
    {

        $data = new History();
        $data->form_action = $this->getRoute() . '.create';
        // Add page type here to indicate that the form.blade.php is in 'add' mode
        $data->page_type = 'add';
        $data->button_text = 'Add';

        return view('backend.histories.form', [
            'data' => $data,
        ]);
    }

    /**
     * Get named route depends on which user is logged in
     *
     * @return String
     */
    private function getRoute()
    {
        return 'histories';
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
            $createNew = History::create($new);
            if ($createNew) {

                $createNew->save();

                // Save log
                $controller = new SaveActivityLogController();
                $controller->saveLog($new, "Create new history QR");

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
            'name' => $type == 'create' ? 'required|string|max:255|unique:histories,name' : 'required|string|max:255|unique:histories,name,' . $data['id'],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data = History::find($id);
        $data->form_action = $this->getRoute() . '.update';
        // Add page type here to indicate that the form.blade.php is in 'edit' mode
        $data->page_type = 'edit';
        $data->button_text = 'Edit';

        return view('backend.histories.form', [
            'data' => $data,
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
            $currentData = History::find($request->get('id'));
            if ($currentData) {
                $this->validator($new, 'update')->validate();

                // If user change name will change name also on user DB
                $changeName = User::where('name', $currentData->name)->first();
                if($changeName) {
                    $changeName->name = $new['name'];
                    $changeName->save();
                }

                // Update
                $currentData->update($new);

                // Save log
                $controller = new SaveActivityLogController();
                $controller->saveLog($new, "Update history QR");

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
            // Delete
            $new = History::find($id);
            $new->delete();

            // Save log
            $controller = new SaveActivityLogController();
            $controller->saveLog($new->toArray(), "Delete history");

            //delete success
            return redirect()->route($this->getRoute())->with('success', Config::get('const.SUCCESS_DELETE_MESSAGE'));
        } catch (Exception $e) {
            // delete failed
            return redirect()->route($this->getRoute())->with('error', Config::get('const.FAILED_DELETE_MESSAGE'));
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route($this->getRoute())->with('error', Config::get('const.ERROR_FOREIGN_KEY'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function import()
    {

        $data = new History();
        $data->form_action = $this->getRoute() . '.importData';
        // Add page type here to indicate that the form.blade.php is in 'add' mode
        $data->page_type = 'add';
        $data->button_text = 'Import';

        return view('backend.histories.import', [
            'data' => $data,
        ]);
    }

    /**
     * Upload and import data from csv file.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function importData(Request $request)
    {
        $errorMessage = '';
        $errorMessageQr = '';
        $errorArr = array();
        $errorArrQr = array();

        // If file extension is 'csv'
        if ($request->hasFile('import')) {

            $file = $request->file('import');

            // File Details
            $extension = $file->getClientOriginalExtension();

            // If file extension is 'csv'
            if ($extension == 'csv') {

                $fp = fopen($file, 'rb');

                $header = fgetcsv($fp, 0, ',');
                $countheader = count($header);

                // Check is csv file is correct format
                if($countheader < 7 && in_array('email', $header, true) && in_array('first_name', $header, true) && in_array('last_name', $header, true) && in_array('role', $header, true) && in_array('password', $header, true) && in_array('can_login', $header, true))
                {
                    // Loop the row data csv
                    while (($csvData = fgetcsv($fp)) !== FALSE) {
                        $csvData = array_map('utf8_encode', $csvData);

                        // Row column length
                        $dataLen = count($csvData);

                        // Skip row if length != 6
                        if (!($dataLen == 6)) {
                            continue;
                        }

                        // Assign value to variables
                        $email = trim($csvData[0]);
                        $first_name = trim($csvData[1]);
                        $last_name = trim($csvData[2]);
                        $canLogin = trim($csvData[5]);
                        $name = $first_name . ' ' . $last_name;
                        $role = trim($csvData[3]);

                        // Insert data to users table
                        if ($canLogin == 'yes') {

                            // Check if any duplicate email
                            if ($this->checkDuplicate($email, 'email')) {
                                $errorArr[] = $email;
                                $str = implode(", ", $errorArr);
                                $errorMessage = '-Some data email already exists ( ' . $str . ' )';
                                continue;
                            }

                            $password = trim($csvData[4]);
                            $hashed = bcrypt($password);

                            $data = array(
                                'email' => $email,
                                'name' => $name,
                                'role' => $role,
                                'password' => $hashed,
                                'image' => 'default-user.png',
                            );

                            // create the user
                            $createNew = User::create($data);

                            // Attach role
                            $createNew->roles()->attach($role);

                            // Save user
                            $createNew->save();
                        }

                        // If Administrator will not import their QR Code
                        if ($role != 1) {
                            // Check if any duplicate name of QR code
                            if ($this->checkDuplicate($name, 'name')) {
                                $errorArrQr[] = $name;
                                $strQr = implode(", ", $errorArrQr);
                                $errorMessageQr = '-Some data name already exists ( ' . $strQr . ' )';
                                continue;
                            }

                            // Insert data to QR code
                            $dataName = array(
                                'name' => $name,
                            );

                            History::create($dataName);
                        }
                    }

                    if ($errorMessage == '' && $errorMessageQr == '') {
                        return redirect()->route($this->getRoute())->with('success', 'Imported was success!');
                    }
                    return redirect()->route($this->getRoute())->with('warning', 'Imported was success! <br><b>Note: We do not import this data data because</b><br>' . $errorMessage . '<br>' . $errorMessageQr);
                }
                return redirect()->route($this->getRoute())->with('error', 'Import failed! You are using the wrong CSV format. Please use the CSV template to import your data.');
            }
            return redirect()->route($this->getRoute())->with('error', 'Please choose file with .CSV extension.');
        }

        return redirect()->route($this->getRoute())->with('error', 'Please select CSV file.');
    }

    /**
     * Function check email is exist or not.
     *
     * @param $data
     * @param $typeCheck
     * @return bool
     */
    public function checkDuplicate($data, $typeCheck){
        if($typeCheck == 'email') {
            $isExists = User::where('email', $data)->first();
        }

        if($typeCheck == 'name') {
            $isExists = History::where('name', $data)->first();
        }

        if($isExists){
            return true;
        }

        return false;
    }
}
