<?php namespace {{app_namespace}}Http\Controllers\{{sub_namespace}};

use Illuminate\Http\Request;

use {{app_namespace}}Http\Controllers\Controller;
use {{model_qualified_name}};

class {{class_name}} extends Controller
{
    private ${{model_instance}};

    public function __construct()
    {
        $this->{{model_instance}} = new {{model_name}};
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (empty($request->all())) {
            return [];
        }

        {{model_instance_attribution}}

        return $this->{{model_instance}}->search();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        {{model_instance_attribution}}

        if ($this->{{model_instance}}->save())
            return $this->{{model_instance}};

        return response()->json($this->{{model_instance}}->getErrors(), 422);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return {{model_name}}::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->{{model_instance}} = {{model_name}}::find($id);

        {{model_instance_pk_attribution}}
        {{model_instance_attribution}}

        if ($this->{{model_instance}}->save())
            return $this->{{model_instance}};

        return response()->json($this->{{model_instance}}->getErrors(), 422);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->{{model_instance}} = {{model_name}}::findOrFail($id);

        return response()->json($this->{{model_instance}}->delete());
    }
}
