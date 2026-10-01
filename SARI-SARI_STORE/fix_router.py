import os

def fix_router_links():
    directory = r'c:\xampp\htdocs\SARISARI\SARISARI\SARI-SARI_STORE\View'
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith('.php'):
                filepath = os.path.join(root, file)
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                new_content = content.replace('../router.php?route=', '../router?route=')
                new_content = new_content.replace('router.php?route=', 'router?route=')
                
                if new_content != content:
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f"Fixed {filepath}")

if __name__ == '__main__':
    fix_router_links()
