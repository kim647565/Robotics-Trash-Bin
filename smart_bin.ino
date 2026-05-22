/*
====================================================
 IoT Smart Waste Bin Monitoring System
 Arduino Uno + HC-SR04 Ultrasonic Sensor
====================================================

 Sensor Connections:
 VCC  -> 5V
 TRIG -> Pin 9
 ECHO -> Pin 10
 GND  -> GND

 Serial Output Format:
 DIST=12.5

 Baud Rate:
 9600
====================================================
*/

#include <Arduino.h>

#define TRIG_PIN 9
#define ECHO_PIN 10

// Number of readings for averaging
const int sampleCount = 5;

float getDistanceCM()
{
  long duration;
  float distance;

  // Trigger ultrasonic pulse
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);

  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);

  digitalWrite(TRIG_PIN, LOW);

  // Read echo pulse
  duration = pulseIn(ECHO_PIN, HIGH, 30000);

  // Timeout protection
  if (duration == 0)
  {
    return -1;
  }

  // Convert to centimeters
  distance = duration * 0.0343 / 2;

  return distance;
}

void setup()
{
  Serial.begin(9600);

  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);

  Serial.println("Smart Waste Bin Monitoring Started");
}

void loop()
{
  float total = 0;
  int validReadings = 0;

  // Noise reduction using averaging
  for (int i = 0; i < sampleCount; i++)
  {
    float reading = getDistanceCM();

    if (reading > 0)
    {
      total += reading;
      validReadings++;
    }

    delay(50);
  }

  if (validReadings > 0)
  {
    float averageDistance = total / validReadings;

    // Send serial data in required format
    Serial.print("DIST=");
    Serial.println(averageDistance, 1);
  }

  // Delay between measurements
  delay(1000);
}