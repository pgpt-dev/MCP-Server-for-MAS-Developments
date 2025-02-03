import socket
import json
import argparse

def send_request(server_ip, server_port, payload):
    """
    Sends a generic request to the server.
    """
    payload_json = json.dumps(payload)

    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as client_socket:
            client_socket.connect((server_ip, server_port))
            client_socket.sendall(payload_json.encode('utf-8'))

            response = b""
            while True:
                part = client_socket.recv(4096)
                response += part
                if len(part) < 4096:
                    break

            return json.loads(response.decode('utf-8'))
    except Exception as e:
        return {"status": "error", "message": str(e)}

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Deletes all chat history from the server.")
    parser.add_argument("--server-ip", required=True, help="IP address of the MCP server")
    parser.add_argument("--server-port", required=True, type=int, help="Port number of the MCP server")
    parser.add_argument("--token", required=True, help="Authentication token")

    args = parser.parse_args()

    payload = {
        "command": "delete_all_chats",
        "token": args.token
    }

    print("📤 Sending request to delete all chats...")
    response = send_request(args.server_ip, args.server_port, payload)
    print("✔️ Response:", json.dumps(response, indent=2))
