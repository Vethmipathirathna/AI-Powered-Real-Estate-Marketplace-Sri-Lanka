"""
RealEstateAI — Local Flask price prediction API.

Loads the trained sklearn Pipeline once at startup and serves /health and /predict.
"""

from __future__ import annotations

from datetime import datetime
from pathlib import Path
from typing import Any

import joblib
import pandas as pd
from flask import Flask, jsonify, request

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "models" / "realestate_price_model.joblib"
MODEL_VERSION = "rf_100_depth20_v1"

FEATURE_COLUMNS = [
    "district",
    "area",
    "perch",
    "bedrooms",
    "bathrooms",
    "kitchen_area_sqft",
    "parking_spots",
    "has_garden",
    "has_ac",
    "water_supply",
    "electricity",
    "floors",
    "year_built",
]

WATER_SUPPLY_VALUES = frozenset({"Pipe-borne", "Well", "Both"})
ELECTRICITY_VALUES = frozenset({"Single phase", "Three phase"})

YEAR_BUILT_MIN = 1800


def _load_model() -> Any:
    if not MODEL_PATH.is_file():
        raise FileNotFoundError(f"Model file not found: {MODEL_PATH.name}")
    return joblib.load(MODEL_PATH)


def _extract_categories(pipeline: Any) -> dict[str, frozenset[str]]:
    preprocessor = pipeline.named_steps["preprocessor"]
    encoder = preprocessor.named_transformers_["cat"]
    categorical_columns = ["district", "area", "water_supply", "electricity"]
    categories: dict[str, frozenset[str]] = {}
    for index, column in enumerate(categorical_columns):
        values = encoder.categories_[index]
        categories[column] = frozenset(str(value) for value in values)
    return categories


MODEL = _load_model()
MODEL_CATEGORIES = _extract_categories(MODEL)


def format_price_lkr(amount: float) -> str:
    return f"LKR {amount:,.2f}"


def parse_bool(value: Any, field: str) -> tuple[bool | None, str | None]:
    if isinstance(value, bool):
        return value, None
    if isinstance(value, int) and value in (0, 1):
        return bool(value), None
    if isinstance(value, float) and value in (0.0, 1.0):
        return bool(value), None
    if isinstance(value, str):
        normalized = value.strip().lower()
        if normalized in {"true", "1", "yes"}:
            return True, None
        if normalized in {"false", "0", "no"}:
            return False, None
    return None, f"{field} must be a boolean value."


def parse_required_string(value: Any, field: str) -> tuple[str | None, str | None]:
    if value is None:
        return None, f"{field} is required."
    if not isinstance(value, str):
        return None, f"{field} must be a non-empty string."
    text = value.strip()
    if text == "":
        return None, f"{field} must be a non-empty string."
    return text, None


def parse_required_number(
    value: Any,
    field: str,
    *,
    integer: bool = False,
    minimum: float | None = None,
    exclusive_minimum: bool = False,
) -> tuple[float | None, str | None]:
    if value is None:
        return None, f"{field} is required."

    if isinstance(value, bool):
        return None, f"{field} must be a number."

    try:
        number = float(value)
    except (TypeError, ValueError):
        return None, f"{field} must be a number."

    if integer and number != int(number):
        return None, f"{field} must be a whole number."

    number = int(number) if integer else number

    if minimum is not None:
        if exclusive_minimum and number <= minimum:
            return None, f"{field} must be greater than {minimum:g}."
        if not exclusive_minimum and number < minimum:
            return None, f"{field} must be at least {minimum:g}."

    return number, None


def validate_payload(payload: Any) -> tuple[dict[str, Any] | None, list[str]]:
    if not isinstance(payload, dict):
        return None, ["Request body must be a JSON object."]

    errors: list[str] = []
    cleaned: dict[str, Any] = {}

    for field in FEATURE_COLUMNS:
        if field not in payload:
            errors.append(f"{field} is required.")

    if errors:
        return None, errors

    district, error = parse_required_string(payload.get("district"), "district")
    if error:
        errors.append(error)
    elif district not in MODEL_CATEGORIES["district"]:
        errors.append("district is not a supported value.")

    area, error = parse_required_string(payload.get("area"), "area")
    if error:
        errors.append(error)

    perch, error = parse_required_number(payload.get("perch"), "perch", exclusive_minimum=True, minimum=0)
    if error:
        errors.append(error)

    bedrooms, error = parse_required_number(
        payload.get("bedrooms"), "bedrooms", integer=True, minimum=0
    )
    if error:
        errors.append(error)

    bathrooms, error = parse_required_number(
        payload.get("bathrooms"), "bathrooms", integer=True, minimum=0
    )
    if error:
        errors.append(error)

    kitchen_area_sqft, error = parse_required_number(
        payload.get("kitchen_area_sqft"), "kitchen_area_sqft", minimum=0
    )
    if error:
        errors.append(error)

    parking_spots, error = parse_required_number(
        payload.get("parking_spots"), "parking_spots", integer=True, minimum=0
    )
    if error:
        errors.append(error)

    has_garden, error = parse_bool(payload.get("has_garden"), "has_garden")
    if error:
        errors.append(error)

    has_ac, error = parse_bool(payload.get("has_ac"), "has_ac")
    if error:
        errors.append(error)

    water_supply, error = parse_required_string(payload.get("water_supply"), "water_supply")
    if error:
        errors.append(error)
    elif water_supply not in WATER_SUPPLY_VALUES:
        errors.append("water_supply must be one of: Pipe-borne, Well, Both.")

    electricity, error = parse_required_string(payload.get("electricity"), "electricity")
    if error:
        errors.append(error)
    elif electricity not in ELECTRICITY_VALUES:
        errors.append("electricity must be one of: Single phase, Three phase.")

    floors, error = parse_required_number(payload.get("floors"), "floors", integer=True, minimum=1)
    if error:
        errors.append(error)

    max_year = datetime.now().year + 1
    year_built, error = parse_required_number(
        payload.get("year_built"), "year_built", integer=True, minimum=YEAR_BUILT_MIN
    )
    if error:
        errors.append(error)
    elif year_built is not None and year_built > max_year:
        errors.append(f"year_built must be at most {max_year}.")

    if errors:
        return None, errors

    cleaned = {
        "district": district,
        "area": area,
        "perch": float(perch),
        "bedrooms": int(bedrooms),
        "bathrooms": int(bathrooms),
        "kitchen_area_sqft": float(kitchen_area_sqft),
        "parking_spots": int(parking_spots),
        "has_garden": bool(has_garden),
        "has_ac": bool(has_ac),
        "water_supply": water_supply,
        "electricity": electricity,
        "floors": int(floors),
        "year_built": int(year_built),
    }
    return cleaned, []


def build_feature_frame(features: dict[str, Any]) -> pd.DataFrame:
    row = {column: features[column] for column in FEATURE_COLUMNS}
    return pd.DataFrame([row], columns=FEATURE_COLUMNS)


def create_app() -> Flask:
    app = Flask(__name__)

    @app.get("/health")
    def health():
        return jsonify(
            {
                "status": "ok",
                "service": "RealEstateAI Price Prediction API",
                "model_loaded": MODEL is not None,
            }
        )

    @app.post("/predict")
    def predict():
        if not request.is_json:
            return jsonify({"success": False, "error": "Invalid input data."}), 400

        payload = request.get_json(silent=True)
        features, errors = validate_payload(payload)
        if features is None:
            return jsonify({"success": False, "error": "Invalid input data.", "details": errors}), 400

        try:
            frame = build_feature_frame(features)
            prediction = float(MODEL.predict(frame)[0])
        except Exception:
            return jsonify({"success": False, "error": "Prediction failed."}), 500

        return jsonify(
            {
                "success": True,
                "predicted_price_lkr": round(prediction, 2),
                "predicted_price_formatted": format_price_lkr(prediction),
                "model_version": MODEL_VERSION,
            }
        )

    @app.errorhandler(400)
    def bad_request(_error):
        return jsonify({"success": False, "error": "Invalid input data."}), 400

    @app.errorhandler(404)
    def not_found(_error):
        return jsonify({"success": False, "error": "Not found."}), 404

    @app.errorhandler(405)
    def method_not_allowed(_error):
        return jsonify({"success": False, "error": "Method not allowed."}), 405

    @app.errorhandler(500)
    def internal_error(_error):
        return jsonify({"success": False, "error": "Prediction failed."}), 500

    return app


app = create_app()


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=False)
