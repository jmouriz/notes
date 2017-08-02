<?php
require 'vendor/autoload.php';

class Model {
   protected $fields = array();
   protected $connection;
   protected $table;
   protected $data;
   protected $key;

   public function __construct() {
      $options = array(
         PDO::ATTR_PERSISTENT => true,
         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      );
      $this->connection = new PDO('sqlite:notes.db', null, null, $options);
      $this->data = (object) array();
   }

   public function __destruct() {
      $this->connection = null;
   }

   public function __set($key, $value) {
      if (in_array($key, $this->fields)) {
         $this->data->$key = $value;
      }
   }

   public function __get($key) {
      if (property_exists($this->data, $key)) {
         return $this->data->$key;
      }
   }

   protected function query($sql) {
      $query = $this->connection->prepare($sql);
      foreach ($this->data as $key => $value) {
         if (preg_match("/:$key/", $sql)) {
            $query->bindValue(":$key", $value);
         }
      }
      $query->execute();
      return $query;
   }

   public function get() {
      return $this->data;
   }

   public function key($value) {
      $key = $this->key;
      $this->$key = $value;
   }

   public function all() {
      $query = $this->query("select * from {$this->table}");
      return $query->fetchAll(PDO::FETCH_ASSOC);
   }

   public function select($key) {
      $this->key($key);
      $query = $this->query("select * from {$this->table} where {$this->key} = :{$this->key}");
      $data = $query->fetch(PDO::FETCH_OBJ);
      if ($data) {
         $this->data = $data;
      }
      return $data;
   }

   public function insert() {
      $fields = implode(',', $this->fields);
      $values = implode(',', array_map(function ($field) {
         return ":$field";
      }, $this->fields));
      $this->query("insert into {$this->table} ($fields) values ($values)");
      $this->key($this->connection->lastInsertId());
   }

   public function delete($key) {
      $this->key($key);
      $this->query("delete from {$this->table} where {$this->key} = :{$this->key}");
   }
}

class Note extends Model {
   protected $fields = array('id', 'date', 'title', 'detail');
   protected $table = 'notes';
   protected $key = 'id';

   public function all() {
      $query = $this->query("select * from {$this->table} order by date desc");
      return $query->fetchAll(PDO::FETCH_ASSOC);
   }
}

$note = new Note();
$action = mof\input('action');
switch ($action) {
   case 'save':
      $id = mof\input('id');
      if ($id) {
         $note->delete($id);
         $status = 'updated';
      } else {
         $status = 'inserted';
      }
      $detail = mof\input('detail');
      $note->title = trim(strtok($detail, "\n"), "#*- \t\n\r\0\x0B");
      $note->detail = $detail;
      $note->date = date('U') * 1000;
      $note->insert();
      mof\json(array('status' => $status, 'note' => $note->get()));
   case 'delete':
      $id = mof\input('id');
      $note->delete($id);
      mof\json(array('status' => 'ok'));
}
?>
<!doctype html>
<html lang="es" ng-app="Application" style="overflow: hidden">
   <head>
      <meta charset="utf-8" />
      <title>Cuaderno de apuntes</title>
  	  	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
      <link rel="shortcut icon" href="images/pencil.ico">
      <link rel="stylesheet" href="bower_components/angular-material/angular-material.min.css" />
      <link rel="stylesheet" href="bower_components/angular-material-icons/angular-material-icons.css" />
      <style>
         table {
            border-collapse: collapse;
         }

         td {
            padding-top: 12px;
            padding-bottom: 12px;
         }

         td:first-child {
            padding-left: 6px;
            padding-right: 32px;
         }

         tr:nth-child(even) {
            background-color: lightgray;
         }

         .ellipsis {
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
         }

         code {
            white-space: pre-wrap;
            word-wrap: break-word;
         }
      </style>
   </head>
   <body ng-controller="Application" ng-cloak>
      <md-toolbar scroll-shrink layout="row">
         <div ng-if="!filter" class="md-toolbar-tools" ng-switch="page">
            <div ng-switch-when="edit">
               <md-button class="md-icon-button" aria-label="Cancel" ng-click="list()">
                  <md-tooltip md-direction="bottom" md-autohide="true">Cancelar</md-tooltip>
                  <ng-md-icon icon="close"></ng-md-icon>
               </md-button>
            </div>
            <div ng-switch-when="view">
               <md-button class="md-icon-button" aria-label="Back" ng-click="list()">
                  <md-tooltip md-direction="bottom" md-autohide="true">Volver</md-tooltip>
                  <ng-md-icon icon="arrow_back"></ng-md-icon>
               </md-button>
            </div>
            <h3 flex class="ellipsis" align="center">{{title}}</h3>
            <div ng-switch-when="list">
               <md-button class="md-icon-button" aria-label="Search" ng-click="search()">
                  <md-tooltip md-direction="bottom" md-autohide="true">Buscar</md-tooltip>
                  <ng-md-icon icon="search"></ng-md-icon>
               </md-button>
            </div>
            <div ng-switch-when="edit">
               <md-button class="md-icon-button" aria-label="Help" ng-click="help()">
                  <md-tooltip md-direction="bottom" md-autohide="true">Ayuda</md-tooltip>
                  <ng-md-icon icon="help_outline"></ng-md-icon>
               </md-button>
               <md-button class="md-icon-button" aria-label="Submit" ng-click="submit()" ng-disabled="invalid()">
                  <md-tooltip md-direction="bottom" md-autohide="true">Enviar</md-tooltip>
                  <ng-md-icon icon="done"></ng-md-icon>
               </md-button>
            </div>
            <div ng-switch-when="view">
               <md-button class="md-icon-button" aria-label="Delete" ng-click="delete()">
                  <md-tooltip md-direction="bottom" md-autohide="true">Eliminar</md-tooltip>
                  <ng-md-icon icon="delete"></ng-md-icon>
               </md-button>
               <md-button class="md-icon-button" aria-label="Edit" ng-click="edit()">
                  <md-tooltip md-direction="bottom" md-autohide="true">Editar</md-tooltip>
                  <ng-md-icon icon="edit"></ng-md-icon>
               </md-button>
            </div>
         </div>
         <div ng-if="filter" class="md-toolbar-tools" style="background: white; color: black">
            <md-input-container md-no-float flex layout="row">
               <input name="filter" ng-model="match.$" placeholder="Buscar..." ng-keydown="press($event)" style="border: none">
            </md-input-container>
            <md-button class="md-icon-button" aria-label="Close" ng-click="clean()">
               <md-tooltip md-direction="bottom" md-autohide="true">Limpiar</md-tooltip>
               <ng-md-icon icon="close"></ng-md-icon>
            </md-button>
         </div>
      </md-toolbar>

      <md-progress-linear md-mode="indeterminate" ng-if="busy"></md-progress-linear>

      <div style="display: none">
         <div id="help" class="md-dialog-container">
            <md-dialog aria-label="Help">
               <md-toolbar>
                  <div class="md-toolbar-tools">
                     <h2>Ayuda</h2>
                     <span flex></span>
                     <md-button class="md-icon-button" aria-label="Close" ng-click="close()">
                        <md-tooltip md-direction="bottom" md-autohide="true">Cerrar</md-tooltip>
                        <ng-md-icon icon="close"></ng-md-icon>
                     </md-button>
                  </div>
               </md-toolbar>
               <md-dialog-content style="padding: 16px">
                  <table>
                     <tbody>
                        <tr>
                           <td>*Cursiva*</td>
                           <td><em>Cursiva</em></td>
                        </tr>
                        <tr>
                           <td>**Negrita**</td>
                           <td><strong>Negrita</strong></td>
                        </tr>
                        <tr>
                           <td># Encabezado 1</td>
                           <td><h1>Encabezado 1</h1></td>
                        </tr>
                        <tr>
                           <td>## Encabezado 2</td>
                           <td><h2>Encabezado 2</h2></td>
                        </tr>
                        <tr>
                           <td>[Enlace](http://www.google.com)</td>
                           <td><a href="http://www.google.com">Link</a></td>
                        </tr>
                        <tr>
                           <td>![Imágen](images/pencil.png)</td>
                           <td><img src="images/pencil.png" width="36" height="36" alt="Imágen"/></td>
                        </tr>
                        <tr>
                           <td>&gt; Bloque sangrado</td>
                           <td><blockquote>Bloque sangrado</blockquote></td>
                        </tr>
                        <tr>
                           <td>
                              * Ítem<br/>
                              * Ítem<br/>
                              * Ítem
                           </td>
                           <td>
                              <ul>
                                 <li>Ítem</li>
                                 <li>Ítem</li>
                                 <li>Ítem</li>
                              </ul>
                           </td>
                        </tr>
                        <tr>
                           <td>
                              1. Uno<br/>
                              2. Dos<br/>
                              3. Tres
                           </td>
                           <td>
                              <ol>
                                 <li>Uno</li>
                                 <li>Dos</li>
                                 <li>Tres</li>
                              </ol>
                           </td>
                        </tr>
                        <tr>
                           <td>---</td>
                           <td><hr /></td>
                        </tr>
                        <tr>
                           <td>`Código en línea` mezcado</td>
                           <td><code>Código en línea</code> mezclado</td>
                        </tr>
                        <tr>
                           <td>
                              ```<br/>
                              # bloque de código<br/>
                              print 'Hola mundo'<br/>
                              exit<br/>
                              ```
                           </td>
                           <td>
                              <code>
                                 # bloque de código<br/>
                                 print 'Hola mundo'<br/>
                                 exit<br/>
                              </code>
                           </td>
                        </tr>
                     </tbody>
                  </table>
               </md-dialog-content>
            </md-dialog>
         </div>
      </div>
      
      <md-content layout="vertical" style="height: calc(100% - 48px)" flex>
         <md-content layout="vertical" style="{{page == 'edit' ? 'height: 100%' : 'display: block'}}" flex>
            <md-card flex>
               <md-card-content style="height: 100%" ng-switch="page">
                  <md-list flex ng-switch-when="list">
                     <md-subheader class="md-no-sticky" ng-if="!result || result.length == 0">
                        <div flex>No hay apuntes para mostrar</div>
                     </md-subheader>
                     <md-list-item class="md-2-line" ng-repeat="note in notes | filter: match as result" ng-click="view(note.id)">
                        <div class="md-list-item-text" layout="column">
                           <h3>{{note.title}}</h3>
                           <p>{{date(note.date)}}</p>
                        </div>
                        <md-menu>
                           <md-button aria-label="Actions" class="md-icon-button" ng-click="$mdMenu.open($event)">
                              <md-tooltip md-direction="bottom" md-autohide="true">Acciones</md-tooltip>
                              <ng-md-icon md-menu-origin icon="more_vert"></ng-md-icon>
                           </md-button>
                           <md-menu-content width="3" >
                              <md-menu-item>
                                 <md-button ng-click="view(note.id)">
                                    <ng-md-icon icon="visibility"></ng-md-icon>
                                    <span>Mostrar</span>
                                 </md-button>
                              </md-menu-item>
                              <md-menu-item>
                                 <md-button ng-click="edit(note.id)">
                                    <ng-md-icon icon="edit"></ng-md-icon>
                                    <span>Editar</span>
                                 </md-button>
                              </md-menu-item>
                              <md-menu-item>
                                 <md-button ng-click="delete(note.id)">
                                    <ng-md-icon icon="delete"></ng-md-icon>
                                    <span>Eliminar</span>
                                 </md-button>
                              </md-menu-item>
                           </md-menu-content>
                        </md-menu>
                     </md-list-item>
                  </md-list>
                  <textarea ng-switch-when="edit" name="detail" required ng-model="data.detail" flex style="border: none; outline: none; width: 100%; resize: none; height: 100%; max-height: 100%"></textarea>
                  <div ng-switch-when="view" ng-bind-html="detail"></div>
               </md-card-content>
            </md-card>
         </md-content>
      </md-content>

      <md-button class="md-fab md-primary" aria-label="Add" ng-if="page == 'list'" ng-click="add()" auto-grow style="position: absolute; bottom: 16px; right: 16px;">
         <md-tooltip md-direction="bottom" md-autohide="true">Nuevo</md-tooltip>
         <ng-md-icon icon="add"></ng-md-icon>
      </md-button>

      <script src="bower_components/jquery/dist/jquery.min.js"></script>
      <script src="bower_components/moment/min/moment-with-locales.min.js"></script>
      <script src="bower_components/svg-morpheus/compile/minified/svg-morpheus.js"></script>
      <script src="bower_components/showdown/dist/showdown.min.js"></script>

      <script src="bower_components/angular/angular.min.js"></script>
      <script src="bower_components/angular-aria/angular-aria.min.js"></script>
      <script src="bower_components/angular-animate/angular-animate.min.js"></script>
      <script src="bower_components/angular-messages/angular-messages.min.js"></script>
      <script src="bower_components/angular-material/angular-material.min.js"></script>
      <script src="bower_components/angular-material-icons/angular-material-icons.min.js"></script>

      <script>
         moment.locale('es');

         var application = angular.module('Application', ['ngMaterial', 'ngMdIcons', 'ngAnimate', 'ngMessages']);

         application.controller('Application', ['$scope', '$timeout', '$http', '$sce', '$mdDialog', function($scope, $timeout, $http, $sce, $mdDialog) {
            $scope.busy = false;
            $scope.notes = <?php print json_encode($note->all()); ?>;
            $scope.data = {};

            $scope.note = function(id) {
               if (!id) {
                  id = $scope.data.id;
               } else {
                  $scope.data.id = id;
               }
               return $scope.notes.find(function(each) {
                  return each.id == id;
               });
            };

            $scope.submit = function() {
               $scope.busy = true;
               $http.post(location.href+'?action=save', $scope.data).then(function(response) {
                  if (response.status == 200) {
                     var index = $scope.notes.indexOf($scope.data);
                     if (response.data.status == 'inserted') {
                        $scope.notes.push(response.data.note);
                     } else {
                        $scope.notes[index] = response.data.note;
                     }
                     $scope.busy = false;
                     $scope.list();
                  }
               });
            };

            $scope.search = function() {
               $scope.filter = true;
               $timeout(function() {
                  angular.element('input').focus();
               });
            };

            $scope.date = function(date) {
               return moment(parseInt(date)).fromNow();
            };

            $scope.help = function() {
               $mdDialog.show({
                  contentElement: '#help',
                  parent: angular.element(document.body),
                  targetEvent: event,
                  clickOutsideToClose: true
               });
            };

            $scope.close = function() {
               $mdDialog.cancel();
            };

            $scope.list = function() {
               $scope.title = 'Cuaderno de apuntes';
               $scope.page = 'list';
               $scope.filter = false;
               delete $scope.data.id;
            };

            $scope.clean = function() {
               $scope.match = {};
               $scope.list();
            };

            $scope.view = function(id) {
               var note = $scope.note(id);
               var converter = new showdown.Converter();
               var html = converter.makeHtml(note.detail);
               $scope.filter = false;
               $scope.detail = $sce.trustAsHtml(html);
               $scope.page = 'view';
               $scope.title = note.title;
            };

            $scope.add = function() {
               $scope.title = 'Nuevo apunte';
               $scope.page = 'edit';
               $scope.filter = false;
               $scope.data.detail = '';
               $timeout(function() {
                  angular.element('textarea').focus();
               });
            };

            $scope.edit = function(id) {
               $scope.data = $scope.note(id);
               $scope.title = 'Editar apunte';
               $scope.page = 'edit';
               $timeout(function() {
                  angular.element('textarea').focus();
               });
            };

            $scope.delete = function(id) {
               var note = $scope.note(id);
               var dialog = $mdDialog.confirm({
                  title: '¿Borrar apunte "' + note.title + '"?',
                  textContent: 'Al borrar el apunte se perderá todo su contenido',
                  ariaLabel: 'Delete',
                  targetEvent: event,
                  ok: 'Borrar',
                  cancel: 'Conservar'
               });

               $mdDialog.show(dialog).then(function() {
                  $scope.busy = true;
                  $http.post(location.href+'?action=delete', { id: note.id }).then(function(response) {
                     if (response.status == 200) {
                        var index = $scope.notes.indexOf(note);
                        $scope.notes.splice(index, 1);
                        $scope.busy = false;
                        $scope.list();
                     }
                  });
               }, function() {
               });
            };

            $scope.clean();
         }]);
      </script>
   </body>
</html>

