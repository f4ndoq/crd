<?php

namespace Fandoq\Crudgenerator\commands;

use File;
use Illuminate\Console\Command;

class maincrud extends Command
{
//    public $fields = [];
    public $fields;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jojo:generate
                            {name : The name of the Crud.}
                            {--fields= : Field names for the form & migration.}
                            {--fields_from_file= : Fields from a json file.}
                            {--validations= : Validation rules for the fields.}
                            {--controller-namespace= : Namespace of the controller.}
                            {--model-namespace= : Namespace of the model inside "app" dir.}
                            {--pk=id : The name of the primary key.}
                            {--pagination=25 : The amount of models per page for index pages.}
                            {--indexes= : The fields to add an index to.}
                            {--foreign-keys= : The foreign keys for the table.}
                            {--relationships= : The relationships for the model.}
                            {--route=yes : Include Crud route to routes.php? yes|no.}
                            {--route-group= : Prefix of the route group.}
                            {--view-path= : The name of the view path.}
                            {--form-helper=html : Helper for generating the form.}
                            {--localize=no : Allow to localize? yes|no.}
                            {--locales=en : Locales language type.}
                            {--soft-deletes=no : Include soft deletes fields.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Crud including controller, model, views & migrations.';

    /** @var string  */
    protected $routeName = '';

    /** @var string  */
    protected $controller = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $modelName = ucfirst(str_singular($name));
        $migrationName = str_plural(snake_case($name));
        $tableName = $migrationName;
        $validations = "";

        $routeGroup = $this->option('route-group');
        $this->routeName = ($routeGroup) ? $routeGroup . '/' . snake_case($name, '-') : snake_case($name, '-');
        $perPage = intval($this->option('pagination'));

        $controllerNamespace = ($this->option('controller-namespace')) ? $this->option('controller-namespace') . '\\' : 'Admin\\';
        $modelNamespace = ($this->option('model-namespace')) ? trim($this->option('model-namespace')) . '\\' : '';

        if (empty($this->option('fields')))
        {
            $this->info('Specify fields for the model (skip id & timestamp fields, we will add it automatically)');
            $this->info('Read docs carefully to specify field inputs)');
            $this->info('e.g: name:[required] db_type:[required , ForExample:string|text|integer] options[optional]');
            $this->line("description text nullable");
            $this->alert('Enter "exit" to finish');


            while (true) {
                $fieldInputStr = $this->ask('Field: (name db_type options)', '');


                if (empty($fieldInputStr) || $fieldInputStr == false || $fieldInputStr == 'exit') {
                    $this->line($fieldInputStr);
                    break;
                }

                if (!$this->validateFieldInput($fieldInputStr)) {
                    $this->error('Invalid Input. Try again');
                    continue;
                }
                $val = explode(' ',$fieldInputStr);
                $validationsAsk = $this->ask('Enter validations: ', false);
                $validations .= ($validationsAsk == false) ? '' : $val[0]."#".$validationsAsk.";";

                $fieldInputStr=str_replace(' ', '#', $fieldInputStr);
//                $this->fields[] = $fieldInputStr.';';
                $this->fields .= $fieldInputStr.';';

            }
        }
        $this->line('edame coda');

//        $fields = rtrim($this->option('fields'), ';');
        $fields = rtrim($this->fields, ';');

        if ($this->option('fields_from_file')) {
            $fields = $this->processJSONFields($this->option('fields_from_file'));
        }

        $primaryKey = $this->option('pk');
        $viewPath = ($this->option('view-path')) ? $this->option('view-path') : 'admin';

        $foreignKeys = $this->option('foreign-keys');

        if ($this->option('fields_from_file')) {
            $foreignKeys = $this->processJSONForeignKeys($this->option('fields_from_file'));
        }

//        $validations = trim($this->option('validations'));

        if ($this->option('fields_from_file')) {
            $validations = $this->processJSONValidations($this->option('fields_from_file'));
        }

//        $fieldsArray = explode(';', $fields);
        $fieldsArray = explode(';', $fields);

        $fillableArray = [];
        $migrationFields = '';

        foreach ($fieldsArray as $item) {
            $spareParts = explode('#', trim($item));
            $fillableArray[] = $spareParts[0];

            // Process migration fields
            $migrationFields .= $spareParts[0] . '#' . $spareParts[1];
            if (!preg_match('/' . $spareParts[0] . '/', $validations)) {
//                $migrationFields .= '#nullable';
                $migrationFields .= '';
            }
            $migrationFields .= ';';
        }

        $commaSeparetedString = implode("', '", $fillableArray);
        $fillable = "['" . $commaSeparetedString . "']";

        $localize = $this->option('localize');
        $locales = $this->option('locales');

        $indexes = $this->option('indexes');
        $relationships = $this->option('relationships');
        if ($this->option('fields_from_file')) {
            $relationships = $this->processJSONRelationships($this->option('fields_from_file'));
        }

        $formHelper = $this->option('form-helper');
//        $softDeletes = $this->option('soft-deletes');
        $softDeletes = "no";
        $this->line($validations);
//        die();
        $this->call('jojo:controller', ['name' => $controllerNamespace . $name . 'Controller', '--crud-name' => $name, '--model-name' => $modelName, '--model-namespace' => $modelNamespace, '--view-path' => $viewPath, '--route-group' => $routeGroup, '--pagination' => $perPage, '--fields' => $fields, '--validations' => $validations , '--r']);
        $this->call('jojo:model', ['name' => $modelNamespace . $modelName, '--fillable' => $fillable, '--table' => $tableName, '--pk' => $primaryKey, '--relationships' => $relationships, '--soft-deletes' => $softDeletes]);
        $this->call('jojo:migration', ['name' => $migrationName, '--schema' => $migrationFields, '--pk' => $primaryKey, '--indexes' => $indexes, '--foreign-keys' => $foreignKeys, '--soft-deletes' => $softDeletes]);
        $this->call('jojo:view', ['name' => $name, '--fields' => $fields, '--validations' => $validations, '--view-path' => $viewPath, '--route-group' => $routeGroup, '--localize' => $localize, '--pk' => $primaryKey, '--form-helper' => $formHelper]);
        if ($this->confirm('Do you execute migrate command?')) {
            $this->call('migrate');
        }
        if ($localize == 'yes') {
            $this->call('crud:lang', ['name' => $name, '--fields' => $fields, '--locales' => $locales]);
        }
        // For optimizing the class loader
        $this->callSilent('optimize');

        // Updating the Http/routes.php file
        $routeFile = app_path('Http/routes.php');

        if (\App::VERSION() >= '5.3') {
            $routeFile = base_path('routes/web.php');
        }

        if (file_exists($routeFile) && (strtolower($this->option('route')) === 'yes')) {
            $this->controller = ($controllerNamespace != '') ? $controllerNamespace . '\\' . $name . 'Controller' : $name . 'Controller';

            $isAdded = File::append($routeFile, "\n" . implode("\n", $this->addRoutes()));

            if ($isAdded) {
                $this->info('Crud/Resource route added to ' . $routeFile);
            } else {
                $this->info('Unable to add the route to ' . $routeFile);
            }
        }
    }

    /**
     * Add routes.
     *
     * @return  array
     */
    protected function addRoutes()
    {
        return ["Route::resource('" . $this->routeName . "', '" . $this->controller . "');"];
    }

    /**
     * Process the JSON Fields.
     *
     * @param  string $file
     *
     * @return string
     */
    protected function processJSONFields($file)
    {
        $json = File::get($file);
        $fields = json_decode($json);

        $fieldsString = '';
        foreach ($fields->fields as $field) {
            if ($field->type == 'select') {
                $fieldsString .= $field->name . '#' . $field->type . '#options=' . json_encode($field->options) . ';';
            } else {
                $fieldsString .= $field->name . '#' . $field->type . ';';
            }
        }

        $fieldsString = rtrim($fieldsString, ';');

        return $fieldsString;
    }

    /**
     * Process the JSON Foreign keys.
     *
     * @param  string $file
     *
     * @return string
     */
    protected function processJSONForeignKeys($file)
    {
        $json = File::get($file);
        $fields = json_decode($json);

        if (! property_exists($fields, 'foreign_keys')) {
            return '';
        }

        $foreignKeysString = '';
        foreach ($fields->foreign_keys as $foreign_key) {
            $foreignKeysString .= $foreign_key->column . '#' . $foreign_key->references . '#' . $foreign_key->on;

            if (property_exists($foreign_key, 'onDelete')) {
                $foreignKeysString .= '#' . $foreign_key->onDelete;
            }

            if (property_exists($foreign_key, 'onUpdate')) {
                $foreignKeysString .= '#' . $foreign_key->onUpdate;
            }

            $foreignKeysString .= ',';
        }

        $foreignKeysString = rtrim($foreignKeysString, ',');

        return $foreignKeysString;
    }

    /**
     * Process the JSON Relationships.
     *
     * @param  string $file
     *
     * @return string
     */
    protected function processJSONRelationships($file)
    {
        $json = File::get($file);
        $fields = json_decode($json);

        if (!property_exists($fields, 'relationships')) {
            return '';
        }

        $relationsString = '';
        foreach ($fields->relationships as $relation) {
            $relationsString .= $relation->name . '#' . $relation->type . '#' . $relation->class . ';';
        }

        $relationsString = rtrim($relationsString, ';');

        return $relationsString;
    }

    /**
     * Process the JSON Validations.
     *
     * @param  string $file
     *
     * @return string
     */
    protected function processJSONValidations($file)
    {
        $json = File::get($file);
        $fields = json_decode($json);

        if (!property_exists($fields, 'validations')) {
            return '';
        }

        $validationsString = '';
        foreach ($fields->validations as $validation) {
            $validationsString .= $validation->field . '#' . $validation->rules . ';';
        }

        $validationsString = rtrim($validationsString, ';');

        return $validationsString;
    }



    private function validateFieldInput($fieldInputStr)
    {
        $fieldInputs = explode(' ', $fieldInputStr);

        if (count($fieldInputs) < 2) {
            return false;
        }

        return true;
    }
}
