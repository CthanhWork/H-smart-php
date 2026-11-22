import unittest

from app.application.use_cases.parse_product_search import _fallback


class ProductSearchIntentTests(unittest.TestCase):
    def test_vietnamese_price_range_becomes_vnd_filters(self):
        parsed = _fallback("Có bàn học nào từ 200 đến 700 nghìn không?", {})
        self.assertEqual(parsed["min_price"], 200_000)
        self.assertEqual(parsed["max_price"], 700_000)
        self.assertIn("ban hoc", parsed["keyword"])

    def test_follow_up_selects_product_from_latest_result_index(self):
        parsed = _fallback("Cho mình xem cái thứ hai", {"max_price": 500_000, "sort": "newest"})
        self.assertEqual(parsed["intent"], "select_product")
        self.assertEqual(parsed["reference_index"], 2)
        self.assertEqual(parsed["max_price"], 500_000)

    def test_condition_refinement_keeps_previous_limits(self):
        parsed = _fallback("Chỉ lấy hàng còn tốt", {"max_price": 500_000})
        self.assertEqual(parsed["condition"], "good")
        self.assertEqual(parsed["max_price"], 500_000)


if __name__ == "__main__":
    unittest.main()
