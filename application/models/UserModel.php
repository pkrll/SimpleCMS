<?php
/**
 * User Model
 *
 * Add users, change permissions for both users and resources (pages)
 * and login/logout.
 *
 * @version 1.0
 * @author  Ardalan Samimi
 * @since   Available since 0.10.2
 */
use hyperion\core\Model;
use saturn\session\Session;
class UserModel extends Model {

    /**
     * Find and include the controllers contained
     * in the CONTROLLERS folder. Return only the
     * the classes names.
     *
     * @return  $array
     */
    private function findAndIncludeControllers () {
        $eturnValue     = array();
        $fileExtension  = ".php";
        // Scan the dir and return only the desired files with
        // array_filter, using closure to pass the above variable
        // as an extra parameter.
        $folderContents = array_filter(scandir(CONTROLLERS), function($file) use ($fileExtension) {
            if (substr($file, -4) === $fileExtension)
                return $file;
            return 0;
        });
        // Include the controllers that are not already been
        // loaded (which should be all except UserController).
        foreach ($folderContents AS $key => $controller) {
            $pathToController   = CONTROLLERS . "/{$controller}";
            $includedFiles      = get_included_files();
            if (in_array($pathToController, $includedFiles) === FALSE)
                include $pathToController;
            $className = substr($controller, 0, -strlen($fileExtension));
            $returnValue[]  = $className;
        }

        return $returnValue;
    }

    /**
     * Returns a string containing N number of
     * unnamed placeholders, i.e (?,? ...)
     *
     * @param   integer $count
     * @param   bool    $withBrackets
     * @return  string
     */
    private function createMarkers ($count = 0, $withBrackets = TRUE) {
        $markers = array_fill(0, $count, '?');
        $markers = ($withBrackets) ? '(' . implode(", ", $markers) . ')' : implode(", ", $markers);
        return $markers;
    }

    /**
     * Get Resource permissions
     *
     * @param   string  $name
     * @return  mixed
     */
    private function getPermission ($name = NULL) {
        if ($name === NULL)
            return NULL;
        $sqlQuery = "SELECT permissionLevel FROM Resources WHERE name = :name";
        $response = $this->read($sqlQuery, array("name" => $name), FALSE);
        return $response;
    }

    /**
     * Fetch all the protected controller functions.
     *
     * @return  $array;
     */
    public function getMethods () {
        $methods    = array();
        $suffix     = "Controller";
        // Include controllers inside the CONTROLLERS folder
        $controllers = $this->findAndIncludeControllers();
        // List all the controller methods using reflection
        foreach ($controllers as $controller) {
            $Reflection     = new ReflectionClass($controller);
            $classMethods   = $Reflection->getMethods(ReflectionMethod::IS_PROTECTED);
            // Remove the Controller suffix from class name.
            $className = substr($controller, 0, -strlen($suffix));
            // Add the class methods to the return array, but remove any
            // method that are declared in the base class.
            foreach ($classMethods AS $classMethod) {
                if ($classMethod->class === $controller) {
                    // Create the array containing all the apps
                    // methods (resources) and their permissions
                    // if any are set.
                    $methods[] = array(
                        "class"         => $className,
                        "resource"      => $className.':'.$classMethod->name,
                        "permission"    => $this->getPermission($className.':'.$classMethod->name)
                    );
                }
            }
        }

        return $methods;
    }

    /**
     * Change the Resources permissions
     *
     * @param   array   $data
     * @return  mixed
     */
    public function changePermission ($data = NULL) {
        if ($data === NULL)
            return NULL;

        foreach ($data['resource'] AS $key => $item) {
            $resource[] = array(
                "name"  => $item,
                "permissionLevel" => $data['permission'][$key]
            );
        }
        // Create the unnamed palceholders, based on the
        // number of values the row will take, (?,?...);
        $markers = $this->createMarkers(count($resource[0]));
        // The amount of placeholders must match the amount
        // of values that are to be inserted in the VALUES-
        // clause. Create the array with array_fill() and
        // join the array with the query-string.
        $sqlClause = array_fill(0, count($resource), $markers);
        $sqlQuery = "INSERT INTO Resources (name, permissionLevel) VALUES " . implode(", ", $sqlClause) . " ON DUPLICATE KEY UPDATE permissionLevel = VALUES(permissionLevel)";
        // Prepare for take-off
        $this->prepare($sqlQuery);
        // Bind the values
        $position = 1;
        $columns = array_keys($resource[0]);
        foreach ($resource AS $key => $item)
            foreach ($columns AS $column)
                $this->bindValue($position++, $item[$column]);
        $error = $this->write();
        return (isset($error['error'])) ? $error : TRUE;
    }

    /**
     * User login function
     *
     * @param   array   $data
     * @return  array   |   bool
     */
    public function login ($data = NULL) {
        if ($data['username'] === NULL || $data['password'] === NULL)
            return $array["error"]["message"] = "Login credentials";

        $sqlQuery = "SELECT id, CONCAT_WS(' ', firstname, lastname) AS name, permission from Users WHERE username = :username AND password = :password LIMIT 1";
		$sqlParam = array("username" => $data['username'], "password" => md5($data['password']));
		$response = $this->read($sqlQuery, $sqlParam, FALSE);
        // Set the session variables
        if ($response !== FALSE && !empty($response['id'])) {
            Session::set("user_id", $response['id']);
            Session::set("user_permission", $response['permission']);
            Session::set("username", $data['username']);
            Session::set("name", $response['name']);

            return TRUE;
        }

        return FALSE;
    }

    /**
     * User logout function
     *
     */
    public function logout () {
        Session::clear("user_id");
        Session::clear("user_permission");
        Session::clear("username");
        Session::clear("name");
    }
}
