<?php

namespace WerdenIt\EntityAdmin\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminController
{
    protected $model;
    protected $route;

    protected $limit = 25;
    protected $columns = [];
    protected $form = [];
    protected $actions = ['V', 'E', 'D'];

    private $translatableContract = 'TranslatableContract';

    protected function __construct()
    {
        $this->locales = [];
    }

    protected function index(Request $request)
    {
        $reflection = new \ReflectionClass($this->model);
        $model = $reflection->newInstance();

        $pluralName = $model::getPluralName();
        $singularName = $model::getSingularName();

        if($request->has('limit'))
            $limit = $request->input('limit');
        else
            $limit = $this->limit;

        if($request->has('page'))
            $page = $request->input('page');
        else
            $page = 1;

        $results = $model->paginate($limit, ['*'], 'page', $page);

        $data = [
            'pluralName' => $pluralName,
            'singularName' => $singularName,
            'results' => $results,
            'route' => url($this->route),
            'columns' => $this->columns,
            'actions' => $this->actions,
            'side_menu' => $this->sideMenu(),
            'first_page_name' => 'Dashboard',
            'second_page_name' => '',
            'third_page_name' => '',
        ];

        return view('default.index', $data);
    }

    protected function create(Request $request)
    {
        $reflection = new \ReflectionClass($this->model);
        $model = $reflection->newInstance();

        $pluralName = $model::getPluralName();
        $singularName = $model::getSingularName();

        $data = [];

        $data = [
            'pluralName' => $pluralName,
            'singularName' => $singularName,
            'form' => $this->form,
            'route' => url($this->route),
            'item' => $reflection->newInstance(),
            'side_menu' => $this->sideMenu(),
            'first_page_name' => 'Dashboard',
            'second_page_name' => '',
            'third_page_name' => '',
        ];

        return view('default.edit', $data);
    }

    protected function edit($id, Request $request)
    {
        $reflection = new \ReflectionClass($this->model);
        $model = $reflection->newInstance();
        $item = $model::find($id);

        $pluralName = $model::getPluralName();
        $singularName = $model::getSingularName();

        $data = [];

        foreach($this->form as $i=>$form){
            if(isset($form['model'])){
                $reflectionList = new \ReflectionClass($form['model']);
                $modelList = $reflectionList->newInstance();

                $this->form[$i]['model'] = $modelList::all();
            }
        }

        $data = [
            'pluralName' => $pluralName,
            'singularName' => $singularName,
            'form' => $this->form,
            'route' => url($this->route),
            'item' => $item,
            'side_menu' => $this->sideMenu(),
            'first_page_name' => 'Dashboard',
            'second_page_name' => '',
            'third_page_name' => '',
        ];

        return view('default.edit', $data);
    }

    protected function store(Request $request)
    {
        $reflection = new \ReflectionClass($this->model);
        $model = $reflection->newInstance();

        $validator = $this->validation($model, $request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $fill_data = $this->fillData($model, $request);
        $model->fill($fill_data);
        $model->save();

        $singularName = $model::getSingularName();

        return redirect($this->route)->with('success', trans('general.successfully_created', ['model' => ucfirst($singularName)]));
    }

    protected function update($id, Request $request)
    {
        $reflection = new \ReflectionClass($this->model);
        $model = $reflection->newInstance()::find($id);

        $validator = $this->validation($model, $request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $fill_data = $this->fillData($model, $request);

        foreach($fill_data as $attribute=>$data){
            if(method_exists($model, $attribute)){
                if(is_countable($data)){
                    //associate one to many entity
                }else{
                    //associate one to one entity
                    $model->{$attribute}()->associate($data);
                }
            }else{
                //non complex values
                $model->fill([$attribute => $data]);
            }
        }

        $model->save();

        $singularName = $model::getSingularName();

        return redirect($this->route)->with('success', trans('general.successfully_updated', ['model' => ucfirst($singularName)]));
    }

    protected function validation($model, Request $request)
    {
        $array_rules = [];

        foreach($this->form as $form){
            if(isset($form['validation'])){
                if($model->translatedAttributes && in_array($form['attribute'], $model->translatedAttributes)){
                    foreach($this->locales as $locale){
                        $array_rules[$locale . '_' . $form['attribute']] = $form['validation'];
                    }
                }else{
                    $array_rules[$form['attribute']] = $form['validation'];
                }
            }
        }

        return Validator::make($request->all(), $array_rules);
    }

    protected function fillData($model, Request $request)
    {
        $fill_data = [];

        foreach($this->form as $form){
            if(isset($form['validation'])){
                if($model->translatedAttributes && in_array($form['attribute'], $model->translatedAttributes)){
                    //transalted values
                    foreach($this->locales as $locale){
                        if(!isset($fill_data[$locale]))
                            $fill_data[$locale] = [];

                            $fill_data[$locale][$form['attribute']] = $request->input($locale.'_'.$form['attribute']);
                    }
                }else{
                    if(method_exists($model, $form['attribute'])){
                        if(is_countable($request->input($form['attribute']))){
                            //associate one to many entity
                        }else{
                            //associate one to one entity
                            $reflectionModel = new \ReflectionClass($form['model']);
                            $model = $reflectionModel->newInstance()::find($request->input($form['attribute']));

                            $fill_data[$form['attribute']] = $model;
                        }
                    }else{
                        //plain value
                        $fill_data[$form['attribute']] = $request->input($form['attribute']);
                    }

                }
            }
        }

        return $fill_data;
    }

    public function sideMenu()
    {
        return [
            'dashboard' => [
                'icon' => 'home',
                'page_name' => 'dashboard',
                'route' => '',
                'title' => 'Dashboard'
            ]
        ];
    }
}
