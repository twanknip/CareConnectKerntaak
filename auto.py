from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
import time

# Start browser
driver = webdriver.Chrome()

# Ga naar jouw loginpagina
driver.get("http://localhost/CareConnect/login.php")  # <-- pas dit aan

time.sleep(1)

# Vul username in
username = driver.find_element(By.ID, "username")
username.send_keys("Username")

# Vul password in
password = driver.find_element(By.ID, "password")
password.send_keys("foo' or '1'='1")

# Submit (ENTER of knop)
password.send_keys(Keys.RETURN)

# Of alternatief:
# driver.find_element(By.ID, "loginBtn").click()

time.sleep(5)
driver.quit()