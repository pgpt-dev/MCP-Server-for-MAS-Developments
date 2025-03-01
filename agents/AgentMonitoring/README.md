Nachfolgend findest du eine ausführliche **README.md**, die beschreibt, wie du **drei verschiedene Agenten** (1) den **OpenAI-Compatible API Agent**, (2) den **Chatbot Agent** und (3) den **IoT MQTT Agent** im Hinblick auf **Prometheus**-Monitoring und **Grafana**-Dashboards einrichten kannst. Sie geht darauf ein:

- Wie du die **Agents** startest und wo sie ihre `/metrics`-Informationen zur Verfügung stellen.  
- Wie du in **Prometheus** die `prometheus.yml` anpassen musst, damit es die Metriken aller drei Agenten abfragt.  
- Wie du in **Grafana** die bereitgestellten **Dashboard-JSONs** importierst, welche Panels enthalten sind und welche Parameter du bei Bedarf anpasst.

Speichere den Inhalt einfach als `README.md` in deinem Repository.  

---

# README

## Übersicht
In diesem Repository befinden sich drei unterschiedliche **Agents**, die jeweils Prometheus-Metriken bereitstellen, damit sie in **Prometheus** und **Grafana** überwacht werden können:

1. **OpenAI-Compatible API Agent**  
   - Läuft standardmäßig auf **Port 7777** und stellt `/metrics` bereit.  
2. **Chatbot Agent**  
   - Läuft auf **Port 5001** und stellt `/metrics` bereit (oder kann, je nach Code, den Port aus einer JSON-Konfiguration lesen).  
3. **IoT MQTT Agent**  
   - Läuft auf einem konfigurierten **Prometheus-Port**, z. B. **9101**, mit einem eigenen WSGI-Server.

### Voraussetzungen

- Du benötigst **Python 3.8+** (empfohlen Python 3.10 oder höher).
- Installiere die Abhängigkeiten der drei Agents (z. B. via `pip install -r requirements.txt` oder Poetry – je nachdem, wie dein Projekt strukturiert ist).  
  - Wichtige Bibliothek: **`prometheus_client`** (z. B. `pip install prometheus_client`), damit die Metriken exportiert werden können.
- Du benötigst **Prometheus** und **Grafana**, um die gesammelten Daten zu visualisieren.

---

## 1. OpenAI-Compatible API Agent

- Dieser Agent läuft typischerweise auf **Port 7777** (siehe Code/Config).  
- Er nutzt `start_http_server(7777)` (bzw. einen WSGI-Server) und stellt **Prometheus**-kompatible Metriken unter `http://<host>:7777/metrics` bereit.  

### Starten

```bash
# Wechsle in das Verzeichnis des Agents, z. B.
cd openai_compatible_api_agent

# Starte den Agenten
python main_openai_api.py
```

Wenn alles klappt, siehst du im Log eine Meldung wie:
```
Starting API on http://0.0.0.0:7777
Starting Prometheus metrics server on port 7777...
```
(oder Ähnlich)

### Metriken
- Typische Metriken könnten sein:  
  - `request_count{method,endpoint}`  
  - `request_latency_seconds{method,endpoint}`  
  - `chat_completion_count`  
  - `completion_count`  
  - etc.  

---

## 2. Chatbot Agent

- Läuft standardmäßig auf **Port 5001** (siehe Code/Config).  
- Auch hier wird (je nach Code) ein Prometheus-Server in einem Thread gestartet, der `http://<host>:5001/metrics` ausgibt.  

### Starten

```bash
cd chatbot_agent
python chatbot_main.py
```

Du siehst im Log:
```
Starte API-Server auf 0.0.0.0:5001
Starting Prometheus WSGI server on port 5001...
```
Dann ist `/metrics` verfügbar.

### Metriken
- Typische Metriken in diesem Chatbot:
  - `request_count` / `request_latency_seconds`
  - `chat_completion_count`, `agent_ask_count`
  - etc.  

---

## 3. IoT MQTT Agent

- Im Code wird oft ein konfigurierter **Port** aus einer JSON-Datei (`pgpt_iot_agent.json`) gelesen, z. B.:

```json
{
  "metrics": {
    "port": 9101
  }
  ...
}
```

- Der Agent startet einen kleinen WSGI-Server auf diesem `metrics.port` und liefert die `/metrics`.  
- Im Code sieht das ungefähr so aus:

```python
# iot_mqtt_agent.py
if 'metrics' in config and 'port' in config['metrics']:
    prom_port = config['metrics']['port']
    # Start WSGI...
```

### Starten

```bash
cd iot_mqtt_agent
python iot_mqtt_agent.py --config pgpt_iot_agent.json
```

Im Log kannst du dann sehen:
```
Starting Prometheus WSGI server on port 9101...
```
D. h. `http://<host>:9101/metrics` liefert die Metriken.

### Metriken
- Z. B.:
  - `mqtt_message_count` (Anzahl empfangener MQTT-Nachrichten)  
  - `mqtt_message_latency_seconds` (Histogramm zur Bearbeitungszeit)  

---

## 4. Prometheus konfigurieren

Damit Prometheus all diese Metriken abfragt, musst du in deiner **`prometheus.yml`** jeweils **Targets** eintragen, die auf die Ports der drei Agents zeigen. Beispiel:

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:

  # 1) OpenAI-Compatible API Agent
  - job_name: "openai_compatible_agent"
    scrape_interval: 5s
    static_configs:
      - targets:
          - "192.168.100.185:7777"  # oder "localhost:7777"

  # 2) Chatbot Agent
  - job_name: "chatbot_agent"
    scrape_interval: 5s
    static_configs:
      - targets:
          - "192.168.100.185:5001"  # Dein Chatbot-Port

  # 3) IoT MQTT Agent
  - job_name: "iot_mqtt_agent"
    scrape_interval: 5s
    static_configs:
      - targets:
          - "192.168.100.185:9101"  # Dein IoT Agent Port
```

> Passe **`192.168.100.185`** an deine Umgebung an (oder verwende `localhost`, wenn alles lokal läuft).  
> Achte auf die Ports:
> - `7777` (OpenAI-Compatible),
> - `5001` (Chatbot),
> - `9101` (IoT).  

Starte Prometheus (z. B. per Docker oder direktem Binary). Nach kurzer Zeit solltest du in `http://<prometheus-host>:9090/targets` sehen, dass alle drei Jobs auftauchen und der Status **UP** ist.

---

## 5. Grafana-Dashboards

In diesem Repo gibt es zu jedem Agent ein **Grafana-Dashboard** (JSON-Datei), die du importieren kannst.  

### A) OpenAI-Compatible API Agent Dashboard

- Datei: `dashboards/openai_agent_dashboard.json` (Beispiel)
- Enthält Panels wie:
  - `Request Count (rate)`
  - `Request Latency (p95)`
  - `Chat Completion Count`
  - u. s. w.

#### Import

1. In Grafana: **"Dashboards"** → **"Import"**  
2. Kopiere den Inhalt aus `openai_agent_dashboard.json`  
3. Setze die Data Source (z. B. `"Prometheus"`)  
4. Fertig!

### B) Chatbot Agent Dashboard

- Datei: `dashboards/chatbot_dashboard.json`  
- Zeigt Metriken wie `agent_ask_count`, `request_latency_seconds`, etc.

#### Import

1. Grafana → **"Import"**  
2. JSON einfügen  
3. Prometheus-Data-Source wählen  
4. Dashboard anlegen

### C) IoT MQTT Agent Dashboard

- Datei: `dashboards/iot_mqtt_agent_dashboard.json`  
- Beispielpanels:
  - Rate der MQTT-Nachrichten (`rate(mqtt_message_count[1m])`)
  - Anzahl empfangener MQTT-Nachrichten (Gesamt)
  - Latenzen (`histogram_quantile(0.95, rate(mqtt_message_latency_seconds_bucket[1m]))`)
  - etc.

#### Import

Selbes Prozedere:  
1. **"Dashboards"** → **"Import"**  
2. JSON reinkopieren  
3. Auf **"Prometheus"** als Data Source gehen  
4. Speichern

---

## 6. Wichtige Parameter anpassen

1. **Agent-Ports**  
   - Ändere die Ports in den JSON-Konfigurationen oder direkt im Python-Code, wenn du Kollisionen vermeiden willst.  
   - Standard:
     - **OpenAI-Compatible**: `7777`  
     - **Chatbot**: `5001`  
     - **IoT MQTT**: über `metrics.port` in `pgpt_iot_agent.json`, z. B. `9101`.  

2. **Prometheus `prometheus.yml`**  
   - Passe die **Targets** an die IP/Hostnamen deiner Agents an.  
   - Eventuell änderst du `scrape_interval` (Standard: 15s).

3. **Grafana**  
   - Achte darauf, dass du beim Import der Dashboards im Dropdown **deine** Prometheus-Data-Source auswählst (z. B. `Prometheus (default)` oder `Prometheus-DS`).  
   - Wenn die Panels „No data“ anzeigen, schau in **Prometheus** nach, ob die Metrics existieren.  

---

## 7. Troubleshooting

- **"No data"** in Grafana:
  - Prüfe in **Prometheus** unter `/targets`, ob die drei Jobs **UP** sind.  
  - Prüfe, ob die Metriken im Prometheus Expression Browser sichtbar sind (z. B. `mqtt_message_count`).  
- **Port-Kollision**:
  - Wenn einer der Ports `7777`, `5001`, `9101` schon belegt ist, passe sie in der Konfiguration an.  
- **Authentication-Errors**:
  - Falls der Agent `/metrics` standardmäßig mit API-Key-Auth versieht, musst du das anpassen oder `/metrics` explizit frei machen.  
- **Docker-Umgebung**:
  - Achte auf die IP-Adressen (z. B. `0.0.0.0`) und veröffentliche die Ports (z. B. `-p 5001:5001`).  

---

## Fazit

Mit diesen drei Agents kannst du sowohl die **LLM-spezifischen** (OpenAI-Compatible & Chatbot) als auch die **IoT-spezifischen** (MQTT Agent) Metriken erfassen und in **Grafana** darstellen.  

- **Prometheus** sammelt die Daten über `/metrics`.  
- **Grafana** visualisiert sie mithilfe der oben beschriebenen Dashboards.  

Viel Erfolg beim Einsatz deiner Agents mit umfassendem Monitoring!