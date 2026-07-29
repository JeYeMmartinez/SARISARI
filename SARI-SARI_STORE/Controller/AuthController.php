<?php
// Controller/AuthController.php

class AuthController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Authenticate employee self-service login
     */
    public function loginEmployeePortal($employee_no, $password) {
        if (empty($employee_no) || empty($password)) {
            return "Please fill in all fields.";
        }

        $employee_no = mysqli_real_escape_string($this->conn, trim($employee_no));

        $query = mysqli_query($this->conn, "
            SELECT * FROM employees
            WHERE employee_no = '$employee_no'
            AND status = 'Active'
            LIMIT 1
        ");

        if (mysqli_num_rows($query) == 1) {
            $emp = mysqli_fetch_assoc($query);

            if (password_verify($password, $emp['password'])) {
                $_SESSION['emp_id'] = $emp['employee_id'];
                $_SESSION['emp_no'] = $emp['employee_no'];
                $_SESSION['emp_name'] = $emp['full_name'];
                $_SESSION['emp_email'] = $emp['email'];
                $_SESSION['emp_role'] = 'Employee';

                // Log login action
                require_once __DIR__ . '/../Model/logger.php';
                logAction($this->conn, 1, 'Login', 'employees', $emp['employee_id'], "Employee {$emp['full_name']} logged in to Portal");

                return true; // Success
            } else {
                return "Incorrect password.";
            }
        } else {
            return "Account not found or inactive employee.";
        }
    }

    /**
     * Authenticate employee work portal login
     */
    public function loginEmployeeWork($employee_no, $password) {
        if (empty($employee_no) || empty($password)) {
            return "Please enter both employee number and password.";
        }

        $employee_no = mysqli_real_escape_string($this->conn, trim($employee_no));

        $query = mysqli_query($this->conn, "
            SELECT e.*, p.position_name, d.department_name
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.position_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            WHERE e.employee_no = '$employee_no'
            AND e.status = 'Active'
            LIMIT 1
        ");

        if (mysqli_num_rows($query) == 1) {
            $emp = mysqli_fetch_assoc($query);

            // Verify bcrypt password or default employee ID password
            $pass_valid = (!empty($emp['password']) && password_verify($password, $emp['password'])) ||
                          ($password === $emp['employee_no']);

            if ($pass_valid) {
                $_SESSION['emp_id']          = $emp['employee_id'];
                $_SESSION['emp_no']          = $emp['employee_no'];
                $_SESSION['emp_name']        = $emp['full_name'];
                $_SESSION['emp_email']       = $emp['email'];
                $_SESSION['emp_role']        = $emp['position_name'] ?? 'Employee';
                $_SESSION['emp_position']    = $emp['position_name'] ?? '';
                $_SESSION['emp_department']  = $emp['department_name'] ?? '';
                $_SESSION['is_work_session'] = true;

                // Log the work login action
                require_once __DIR__ . '/../Model/logger.php';
                logAction($this->conn, 1, 'Login', 'employees', $emp['employee_id'], 
                    "Employee {$emp['full_name']} logged into Work Portal ({$emp['position_name']})");

                return $emp; // Success, return employee details for redirection logic
            } else {
                return "Incorrect password. Please check your credentials.";
            }
        } else {
            return "Active employee account not found for this employee number.";
        }
    }
}
