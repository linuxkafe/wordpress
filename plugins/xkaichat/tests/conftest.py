"""Test configuration for all tests."""

import sys
from pathlib import Path

# Add proxy directory to Python path
sys.path.insert(0, str(Path(__file__).parent / "proxy"))