import os
import re
from pathlib import Path
from dotenv import load_dotenv
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel
import sympy as sp
from sympy.parsing.sympy_parser import (
    convert_xor,
    implicit_multiplication_application,
    parse_expr,
    standard_transformations,
)

# Muat .env milik Laravel di direktori induk agar SYMPY_API_SECRET selalu sinkron
load_dotenv(Path(__file__).resolve().parent.parent / ".env")

app = FastAPI(title="TUNTAS Symbolic Verifier")
TRANSFORMS = standard_transformations + (
    implicit_multiplication_application,
    convert_xor,
)

# parse_expr evaluates its input, so anything that reaches it must be small and math-only:
# a single input like 9^9^9 would otherwise tie up the service computing a huge power.
MAX_RAW_LENGTH = 200
MAX_EXPR_LENGTH = 40
ALLOWED_EXPR = re.compile(r"[0-9x+\-*/^().\s]+")
BAD_EXPONENT = re.compile(r"(?:\^|\*\*)\s*+(?!2(?![\d.]))")
LONG_NUMBER = re.compile(r"\d{5,}")


class VerifyRequest(BaseModel):
    student_input: str
    expected_expr: str
    required_form: str  # "integers_pair" | "factored_form" | "roots_set"


def clean_math_text(text: str) -> str:
    text = text.strip().replace("−", "-").replace("–", "-").replace("²", "^2").replace("×", "*")
    if re.search(r"=\s*0\s*$", text):
        text = re.sub(r"=\s*0\s*$", "", text).strip()
    return text


def is_non_math_sentence(text: str) -> bool:
    cleaned = re.sub(r"\b(dan|and|atau|or|x)\b", "", text, flags=re.IGNORECASE)
    return not text or bool(re.search(r"[a-zA-Z]{2,}", cleaned))


def check_pair(raw_in: str, raw_exp: str) -> dict:
    nums_in = [int(n) for n in re.findall(r"-?\d+", raw_in)]
    nums_exp = [int(n) for n in re.findall(r"-?\d+", raw_exp)]

    if len(nums_in) != 2:
        return {
            "status": "ignored_non_answer",
            "is_correct": False,
            "diagnostic_note": "Masukan harus berupa tepat dua bilangan bulat.",
        }

    if sorted(nums_in) == sorted(nums_exp):
        return {
            "status": "correct",
            "is_correct": True,
            "diagnostic_note": "Tepat.",
        }

    a, b = nums_in[0], nums_in[1]
    prod_exp = nums_exp[0] * nums_exp[1]
    sum_exp = nums_exp[0] + nums_exp[1]
    return {
        "status": "wrong",
        "is_correct": False,
        "diagnostic_note": (
            f"Belum tepat: {a} x {b} = {a * b} dan {a} + {b} = {a + b}. "
            f"Kamu butuh hasil kali {prod_exp} dan hasil jumlah {sum_exp}."
        ),
    }


def is_safe_expression(text: str) -> bool:
    """True if the text is short, math-only, and uses no exponent other than 2."""
    return (
        len(text) <= MAX_EXPR_LENGTH
        and ALLOWED_EXPR.fullmatch(text) is not None
        and not BAD_EXPONENT.search(text)
        and not LONG_NUMBER.search(text)
    )


def is_factored(expr: sp.Expr, x: sp.Symbol) -> bool:
    """True for a product of linear factors in x, including a square such as (x - 3)^2."""
    if isinstance(expr, sp.Pow):
        factors = [expr.base]
    elif isinstance(expr, sp.Mul):
        factors = [f.base if isinstance(f, sp.Pow) else f for f in expr.args if f.has(x)]
    else:
        return False
    return bool(factors) and all(sp.degree(f, x) == 1 for f in factors)


def check_factored(raw_in: str, raw_exp: str) -> dict:
    if not is_safe_expression(raw_in):
        return {
            "status": "ignored_non_answer",
            "is_correct": False,
            "diagnostic_note": "Tulis bentuk faktor dengan angka kecil dan pangkat 2 saja, misalnya (x - a)(x - b).",
        }

    x = sp.Symbol("x")
    # evaluate=False keeps (x - 3)(x - 3) as two brackets instead of folding it into a power.
    expr_in = parse_expr(raw_in, local_dict={"x": x}, transformations=TRANSFORMS, evaluate=False)
    expr_exp = parse_expr(raw_exp, local_dict={"x": x}, transformations=TRANSFORMS, evaluate=False)

    if sp.expand(expr_in - expr_exp) != 0:
        return {
            "status": "wrong",
            "is_correct": False,
            "diagnostic_note": "Ekspresi belum ekuivalen dengan bentuk kuadrat pada soal.",
        }

    if not is_factored(expr_in, x):
        return {
            "status": "equivalent_not_factored",
            "is_correct": False,
            "diagnostic_note": "Ekspresi ekuivalen, tetapi belum ditulis sebagai perkalian dua faktor.",
        }

    return {
        "status": "equivalent_and_factored",
        "is_correct": True,
        "diagnostic_note": "Ekspresi ekuivalen dan sudah dalam bentuk faktor.",
    }


def check_roots(raw_in: str, raw_exp: str) -> dict:
    roots_in = sorted([sp.Rational(n) for n in re.findall(r"-?\d+(?:/\d+)?", raw_in)])
    roots_exp = sorted([sp.Rational(n) for n in re.findall(r"-?\d+(?:/\d+)?", raw_exp)])

    if len(roots_in) != len(roots_exp):
        return {
            "status": "ignored_non_answer",
            "is_correct": False,
            "diagnostic_note": "Masukkan kedua nilai akar persamaan.",
        }

    if roots_in == roots_exp:
        return {
            "status": "correct",
            "is_correct": True,
            "diagnostic_note": "Kedua akar persamaan sudah tepat.",
        }

    return {
        "status": "wrong",
        "is_correct": False,
        "diagnostic_note": "Nilai akar belum tepat. Cek nilai x yang membuat masing-masing kurung bernilai 0.",
    }


@app.post("/verify")
def verify(req: VerifyRequest, x_api_key: str = Header(default="")):
    secret = os.getenv("SYMPY_API_SECRET", "")
    if not secret or x_api_key != secret:
        raise HTTPException(status_code=401, detail="Unauthorized")

    raw_in = clean_math_text(req.student_input)
    raw_exp = clean_math_text(req.expected_expr)

    if len(raw_in) > MAX_RAW_LENGTH or is_non_math_sentence(raw_in):
        return {
            "status": "ignored_non_answer",
            "is_correct": False,
            "diagnostic_note": "Diabaikan: bukan jawaban matematika.",
        }

    try:
        if req.required_form == "integers_pair":
            return check_pair(raw_in, raw_exp)
        if req.required_form == "factored_form":
            return check_factored(raw_in, raw_exp)
        if req.required_form == "roots_set":
            return check_roots(raw_in, raw_exp)
        return {
            "status": "ignored_non_answer",
            "is_correct": False,
            "diagnostic_note": "Format verifikasi tidak dikenali.",
        }
    except Exception:
        return {
            "status": "ignored_non_answer",
            "is_correct": False,
            "diagnostic_note": "Ekspresi matematika tidak dapat dibaca.",
        }
