# Page snapshot

```yaml
- heading "Log In" [level=1]
- link "Powered by WordPress":
  - /url: https://wordpress.org/
- paragraph:
  - strong: "Error:"
  - text: The password you entered for the username
  - strong: admin
  - text: is incorrect.
  - link "Lost your password?":
    - /url: http://localhost:8080/wp-login.php?action=lostpassword
- paragraph:
  - text: Username or Email Address
  - textbox "Username or Email Address": admin
- text: Password
- textbox "Password"
- button "Show password"
- paragraph:
  - checkbox "Remember Me"
  - text: Remember Me
- paragraph:
  - button "Log In"
- paragraph:
  - link "Lost your password?":
    - /url: http://localhost:8080/wp-login.php?action=lostpassword
- paragraph:
  - link "← Go to Nuclear Engagement Test":
    - /url: http://localhost:8080/
```