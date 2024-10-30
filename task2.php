<!-- Задание №2.
Создайте класс. Методы класса должный иметь возможность:
- подключаться с БД;
- добавлять пользователя при получении данных с некой формы посредством POST запроса; 
- редактировать данные пользователя;
- получать кастомную выборку по заданному фильтру;
- получать аналитику (например, кто сейчас на сайте);
- на основании полученной аналитики формировать выгрузку (например в CSV или XML файл)
PS: все методы должны уметь обрабатывать ошибки и исключения, информация о ошибках должна писаться в лог-файл.


Решение: -->

<?php

class UserDB
{
    private $connection;

    public function __construct($host, $user, $password, $db)
    {
        mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
        try {
            $this->connection = new mysqli($host, $user, $password, $db);
            if ($this->connection->connect_error) {
                $this->log($this->connection->connect_error);
            } else {
                $this->log('Connect to database ' . $db);
            }
        } catch (mysqli_sql_exception $e) {
            $this->log("Connection to database $db error: " . $e->getMessage());
        };
    }

    public function addUser($FIO, $login, $password, $birthDay, $active)
    {
        try {
            $query = "insert into users (FIO, login, password, birthDay, active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->connection->prepare($query);
            $stmt->bind_param("ssssi", $FIO, $login, $password, $birthDay, $active);

            if ($stmt->execute()) {
                $this->log("User add successfully: $FIO");
            } else {
                $this->log("Failed to add user: $FIO");
            }

            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $this->log('Error add user: ' . $e->getMessage());
        }
    }

    public function editUser($id, $FIO = null, $login = null, $password = null, $birthDay = null, $active = null)
    {
        try {
            $queryValues = [];
            $values = [];
            var_dump($birthDay);
            if ($birthDay == null) {
                $birthDay = date('Y-m-d'); // Устанавливаем текущую дату
            }

            if ($FIO !== null) {
                $queryValues[] = "FIO = ?";
                $values[] = $FIO;
            }
            if ($login !== null) {
                $queryValues[] = "login = ?";
                $values[] = $login;
            }
            if ($password !== null) {
                $queryValues[] = "password = ?";
                $values[] = $password;
            }
            if ($birthDay !== null) {
                $queryValues[] = "birthDay = ?";
                $values[] = $birthDay;
            }
            if ($active !== null) {
                $queryValues[] = "active = ?";
                $values[] = $active;
            }

            if (empty($queryValues)) {
                $this->log("No data for update user with ID $id.");
                return;
            }

            $query = "update users set " . implode(", ", $queryValues) . " WHERE id = ?";
            $values[] = $id;

            $stmt = $this->connection->prepare($query);
            $types = str_repeat('s', count($values) - 1) . 'i';
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $this->log("Update data for user with id = $id");
        } catch (mysqli_sql_exception $e) {
            $this->log("Error edit user with id = $id " . $e->getMessage());
        }
    }

    public function filter($condition)
    {
        try {
            $query = "select * from users where $condition";
            $stmt = $this->connection->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            $this->log("complete filtering data from users table");
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (mysqli_sql_exception $e) {
            $this->log("Error filter function " . $e->getMessage());
            return [];
        }
    }

    public function getAnalytics()
    {
        try {
            $result = $this->connection->query("select COUNT(*) as count from users where active = 1");
            return $result->fetch_assoc();
        } catch (mysqli_sql_exception $e) {
            $this->log("Error getAnalytics function: " . $e->getMessage());
            return [];
        }
    }

    public function exportAnalyticsToXml($fileNameXML = 'export.xml')
    {
        $analytics = $this->getAnalytics();
        $xml = new SimpleXMLElement('<analytics/>');
        $xml->addChild('active_users', htmlspecialchars($analytics['count']));

        try {
            $xml->asXML($fileNameXML);
            $this->log("Файл  был успешно создан");
        } catch (Exception $e) {
            $this->log("Ошибка при создании файла: " . $e->getMessage());
        }
    }

    private function log($message)
    {
        file_put_contents('app.log', date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
    }

    public function close()
    {
        if ($this->connection) {
            if ($this->connection->close()) {
                $this->log('Connection close successfully');
            } else {
                $this->log('Failed to close database connection');
            }
        } else {
            $this->log('No active connection to database');
        }
    }
}
