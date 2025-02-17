import argparse
import json

import httpx
from openai import OpenAI
from pydantic import BaseModel
from enum import Enum

class CarType(str, Enum):
    sedan = "sedan"
    suv = "SUV"
    truck = "Truck"
    coupe = "Coupe"


class CarDescription(BaseModel):
    brand: str
    model: str
    car_type: CarType


json_schema = CarDescription.model_json_schema()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Provide an API key to connect to OpenAI-compatible API.")
    parser.add_argument("--api_key", required=True, help="API key for login")
    parser.add_argument("--base_url", required=True, help="The base url of the VLLM server")
    args = parser.parse_args()


    client = OpenAI(
        base_url=args.base_url,
        api_key=args.api_key,
        http_client=  httpx.Client(verify=False)
    )


    completion = client.chat.completions.create(
        model="/models/mistral-nemo-12b",
        messages=[
            {
                "role": "user",
                "content": "Generate a JSON with the brand, model and car_type of the most iconic car from the 90's",
            }
        ],
        extra_body={"guided_json": json_schema},
    )
    try:
        results = json.loads(completion.choices[0].message.content)
        print(json.dumps(results, indent=2))
    except:
        print(completion.choices[0].message.content)