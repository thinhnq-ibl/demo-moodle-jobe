# MCP Memory & Cross-Session Context Rule

Khi bắt đầu một session mới hoặc khi cần lưu trữ thông tin quan trọng của dự án:

1. **Khôi phục ngữ cảnh (Session mới):**
   - Khi người dùng hỏi về tiến độ, kiến trúc, hoặc các quyết định kỹ thuật từ trước, hãy sử dụng các tool của MCP Memory (`read_graph`, `search_nodes`) để đọc lại tri thức đã lưu.

2. **Ghi nhớ thông tin quan trọng (Persistent Memory):**
   - Khi hoàn thành một tính năng quan trọng, đưa ra quyết định kiến trúc, hoặc người dùng yêu cầu ghi nhớ: sử dụng `create_entities`, `create_relations`, và `add_observations` để lưu lại vào Memory Graph.
   - Nhờ Docker volume mount `/Users/nguyenquocthinh/.mcp-memory-data:/data` (lưu tại `/data/memory.json`), dữ liệu sẽ tồn tại vĩnh viễn xuyên suốt các session.
