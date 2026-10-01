import os

def update_branding():
    directory = r'c:\xampp\htdocs\SARISARI\SARISARI\SARI-SARI_STORE\View'
    files_to_update = [
        r'admin.php',
        r'employee_portal\emp_payslips.php',
        r'Finance_employee\finance_restock.php',
        r'grocery.php',
        r'Inventory_employee\inv_low_stock.php',
        r'jobs.php'
    ]
    
    for file in files_to_update:
        filepath = os.path.join(directory, file)
        if os.path.exists(filepath):
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
            
            new_content = content.replace('Sari-Sari Store Management System', 'Ocart Management System')
            new_content = new_content.replace('Sari-Sari Store', 'Ocart')
            new_content = new_content.replace('SARI-SARI STORE', 'Ocart')
            
            if new_content != content:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print(f"Updated branding in {filepath}")

if __name__ == '__main__':
    update_branding()
