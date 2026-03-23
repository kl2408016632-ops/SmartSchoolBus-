#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <WiFi.h>
#include <HTTPClient.h>

// RFID
#define SS_PIN 5
#define RST_PIN 27

// Buzzer
#define BUZZER_PIN 25
#define BUZZER_CHANNEL 0
#define BUZZER_FREQ 1000
#define BUZZER_RESOLUTION 8

// LCD
LiquidCrystal_I2C lcd(0x27, 16, 2);

MFRC522 rfid(SS_PIN, RST_PIN);

String lastUID = "";

// WiFi
const char* ssid = "muhammad2020_2.4Gzh@unifi";
const char* password = "66636230lj";

// 🌐 SERVER URL - UPDATE THIS WITH YOUR COMPUTER'S IP ADDRESS
// Find your local IP: Open Command Prompt and type: ipconfig
// Look for "IPv4 Address" (usually 192.168.x.x or 172.x.x.x)
// Example: String serverURL = "http://192.168.1.100/SmartSchoolBus/check.php?uid=";
String serverURL = "http://192.168.1.15/SmartSchoolBus/check.php?uid=";

void setup() {
  Serial.begin(115200);

  // RFID
  SPI.begin();
  rfid.PCD_Init();

  // Buzzer
  pinMode(BUZZER_PIN, OUTPUT);
  ledcAttach(BUZZER_PIN, BUZZER_FREQ, BUZZER_RESOLUTION);
  ledcWrite(BUZZER_PIN, 0);  // OFF

  // LCD
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();

  lcd.setCursor(0, 0);
  lcd.print("Connecting WiFi");

  // WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting WiFi");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("\nWiFi Connected");
  Serial.print("ESP32 IP: ");
  Serial.println(WiFi.localIP());

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("WiFi Connected");
  delay(1000);

  // Test buzzer to verify it works
  beep();
  delay(300);
  beep();

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Ready");
  lcd.setCursor(0, 1);
  lcd.print("Scan Card...");
}

// 🔊 Beep (GRANTED)
void beep() {
  ledcWrite(BUZZER_PIN, 255);  // ON
  delay(200);
  ledcWrite(BUZZER_PIN, 0);    // OFF
}

// 🔊 Beep (DENIED)
void beepDenied() {
  beep();
  delay(150);
  beep();
}

// 🌐 Check UID from server
String checkUID(String uid) {
  if (WiFi.status() == WL_CONNECTED) {

    WiFiClient client;
    HTTPClient http;

    String fullURL = serverURL + uid;

    Serial.print("Request URL: ");
    Serial.println(fullURL);

    http.begin(client, fullURL);   // 🔥 FIX HERE

    int httpCode = http.GET();

    Serial.print("HTTP Code: ");
    Serial.println(httpCode);

    if (httpCode > 0) {
      String payload = http.getString();
      http.end();

      payload.trim();
      payload.toUpperCase();  // Convert to uppercase to handle case differences

      Serial.print("Server Response: [");
      Serial.print(payload);
      Serial.println("]");

      return payload;
    } else {
      Serial.println("HTTP request failed");
      http.end();
      return "ERROR";
    }
  }
  return "NO_WIFI";
}
void loop() {
  if (!rfid.PICC_IsNewCardPresent()) return;
  if (!rfid.PICC_ReadCardSerial()) return;

  String currentUID = "";

  for (byte i = 0; i < rfid.uid.size; i++) {
    currentUID += String(rfid.uid.uidByte[i], HEX);
  }

  if (currentUID != lastUID) {

    Serial.print("UID: ");
    Serial.println(currentUID);

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Checking...");

    String result = checkUID(currentUID);

    lcd.clear();

    if (result == "GRANTED") {
      Serial.println("ACCESS GRANTED");

      lcd.setCursor(0, 0);
      lcd.print("ACCESS GRANTED");

      beep();

    } else if (result == "DENIED") {
      Serial.println("ACCESS DENIED");

      lcd.setCursor(0, 0);
      lcd.print("ACCESS DENIED");

      beepDenied();

    } else {
      Serial.println("SERVER ERROR");

      lcd.setCursor(0, 0);
      lcd.print("SERVER ERROR");
    }

    delay(2000);

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Ready");
    lcd.setCursor(0, 1);
    lcd.print("Scan Card...");

    lastUID = currentUID;
  }

  delay(500);
}