import os
import time
import unittest

os.environ.setdefault("SYMPY_API_SECRET", "test-secret")

import main  # noqa: E402  (the secret must be set before main loads .env)

EXPECTED = "(x - 2)(x - 3)"


class CheckFactoredTest(unittest.TestCase):
    def test_ordinary_factor_form_is_accepted(self):
        self.assertTrue(main.check_factored("(x-2)(x-3)", EXPECTED)["is_correct"])

    def test_expanded_form_is_equivalent_but_not_factored(self):
        self.assertEqual(main.check_factored("x^2-5x+6", EXPECTED)["status"], "equivalent_not_factored")

    def test_wrong_factors_are_rejected(self):
        self.assertEqual(main.check_factored("(x-1)(x-6)", EXPECTED)["status"], "wrong")

    def test_perfect_square_is_accepted_however_it_is_written(self):
        for answer in ("(x-3)(x-3)", "(x-3)^2", main.clean_math_text("(x - 3)²")):
            with self.subTest(answer=answer):
                self.assertTrue(main.check_factored(answer, "(x - 3)(x - 3)")["is_correct"])

    def test_huge_exponent_is_refused_before_parsing(self):
        start = time.perf_counter()
        result = main.check_factored("9^9^9", EXPECTED)
        self.assertEqual(result["status"], "ignored_non_answer")
        self.assertLess(time.perf_counter() - start, 1.0)


class GuardTest(unittest.TestCase):
    def test_small_math_passes(self):
        for text in ("(x - 2)(x - 3)", "x^2 - 5x + 6", "(x+4)**2"):
            with self.subTest(text=text):
                self.assertTrue(main.is_safe_expression(text))

    def test_risky_input_is_refused(self):
        for text in ("x^3", "2**10", "9^9^9", "123456x", "x" * 41, "__import__('os')"):
            with self.subTest(text=text):
                self.assertFalse(main.is_safe_expression(text))


class VerifyTest(unittest.TestCase):
    def request(self, student_input, form="factored_form"):
        return main.VerifyRequest(student_input=student_input, expected_expr=EXPECTED, required_form=form)

    def test_wrong_secret_is_rejected(self):
        with self.assertRaises(main.HTTPException):
            main.verify(self.request("(x-2)(x-3)"), x_api_key="wrong")

    def test_very_long_input_is_ignored(self):
        result = main.verify(self.request("1" * 500, "integers_pair"), x_api_key="test-secret")
        self.assertEqual(result["status"], "ignored_non_answer")

    def test_correct_answer_passes_end_to_end(self):
        self.assertTrue(main.verify(self.request("(x - 2)(x - 3)"), x_api_key="test-secret")["is_correct"])


if __name__ == "__main__":
    unittest.main()
