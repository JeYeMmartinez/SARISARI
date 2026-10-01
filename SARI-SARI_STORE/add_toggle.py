import os
import re

def add_password_toggle(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # 1. Add position-relative to the parent div of the password input if it's not already there
    # Look for `<input type="password"`
    
    # Actually, it's safer to just replace `<input type="password" ...>` with the wrapper manually, 
    # but since HTML varies, let's just use regex to find the password input and wrap it or add the icon next to it.
    
    # Let's see how the input is defined.
    # In admin.php:
    # <input type="password" class="form-control" name="password" id="password" required>
    
    # Let's replace:
    # <input type="password" (.*?)>
    # With:
    # <div class="position-relative">
    #     <input type="password" \1 style="padding-right: 40px;">
    #     <i class="bi bi-eye-slash position-absolute top-50 end-0 translate-middle-y me-3" style="cursor: pointer; color: #6c757d; z-index: 10;" onclick="togglePasswordVisibility(this)"></i>
    # </div>
    
    # Wait, the togglePasswordVisibility function in grocery.php takes (inputId, iconElement).
    # If we pass `this` and look at `this.previousElementSibling`, we don't need the ID!
    
    # Let's just write the modified files directly.
    print(f"Read {filepath}")

# Since the HTML structure is specific, let's just do it file by file.
