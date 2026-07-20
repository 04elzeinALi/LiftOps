# Admin Panel Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the real admin dashboard shell (sidebar/topbar matching the approved mockup) and get Buses fully working end-to-end (list, create, edit, delete) as the template the remaining 8 admin resources will copy.

**Architecture:** `AdminLayout` renders the sidebar/topbar and an `<Outlet/>` for nested `/admin/*` routes. 8 of 9 sections route to a shared `ComingSoon` placeholder; Buses gets a real list page (TanStack Query + plain table), a shared create/edit `Dialog`, and a delete `AlertDialog` confirm — all wired to real TanStack Query hooks over the existing Axios client.

**Tech Stack:** Same as the Foundation (React 19, plain JS, react-router-dom, @tanstack/react-query, Tailwind v4, shadcn/ui) — no new dependencies except two more shadcn components (dialog, alert-dialog).

## Global Constraints

- Plain JavaScript only — no TypeScript.
- No automated tests — every task is verified by running the dev server against the real backend and checking real behavior, same as the Foundation plan.
- All styling uses the existing design tokens (`var(--bg)`, `var(--text)`, `var(--accent)`, etc.) and the `font-display`/`font-mono` Tailwind utilities already set up in the Foundation — do not invent new colors.
- Do not install Playwright, Chromium, or any browser-automation/testing tool.
- Do not leave dev servers running or test tokens active after finishing a task — stop servers and revoke any captured tokens before reporting.
- Every commit happens inside `c:\Users\Admin\liftops-frontend\` (this plan touches only the frontend repo, no backend changes).

---

### Task 1: Admin layout shell, placeholder pages, and full route wiring

**Files:**
- Create: `c:\Users\Admin\liftops-frontend\src\layouts\AdminLayout.jsx`
- Create: `c:\Users\Admin\liftops-frontend\src\components\admin\ComingSoon.jsx`
- Modify: `c:\Users\Admin\liftops-frontend\src\App.jsx`
- Delete: `c:\Users\Admin\liftops-frontend\src\pages\admin\DashboardHome.jsx`

**Interfaces:**
- Consumes: `useAuth` from `@/auth/AuthContext`, `ProtectedRoute` from `@/auth/ProtectedRoute` (both from the Foundation)
- Produces: `AdminLayout` (default export, no props, renders `<Outlet/>`) and `ComingSoon` (default export, takes a `title` string prop) — both consumed by `App.jsx`'s route tree and by Task 3

- [ ] **Step 1: Create the AdminLayout component**

Create `c:\Users\Admin\liftops-frontend\src\layouts\AdminLayout.jsx`:
```jsx
import { Outlet, NavLink } from "react-router-dom";
import { useAuth } from "@/auth/AuthContext";

const NAV_GROUPS = [
  {
    label: "Fleet",
    items: [
      { to: "/admin/buses", label: "Buses" },
      { to: "/admin/maintenance", label: "Maintenance" },
    ],
  },
  {
    label: "Network",
    items: [
      { to: "/admin/routes", label: "Routes" },
      { to: "/admin/stations", label: "Stations" },
      { to: "/admin/route-stations", label: "Route Stations" },
    ],
  },
  {
    label: "Schedule",
    items: [
      { to: "/admin/schedules", label: "Schedules" },
      { to: "/admin/schedule-days", label: "Schedule Days" },
      { to: "/admin/trips", label: "Trips" },
    ],
  },
  {
    label: "People",
    items: [{ to: "/admin/drivers", label: "Drivers" }],
  },
];

export default function AdminLayout() {
  const { user, logout } = useAuth();

  return (
    <div className="grid min-h-screen" style={{ gridTemplateColumns: "248px 1fr", background: "var(--bg)" }}>
      <aside
        className="flex flex-col p-4"
        style={{ background: "var(--surface)", borderRight: "1px solid var(--border)" }}
      >
        <div className="mb-7 flex items-center gap-2 px-1">
          <div
            className="h-3 w-3 rounded-full"
            style={{ background: "var(--accent)", boxShadow: "0 0 0 4px color-mix(in srgb, var(--accent) 20%, transparent)" }}
          />
          <span className="font-display text-lg font-extrabold" style={{ color: "var(--text)" }}>
            LIFTOPS
          </span>
        </div>

        {NAV_GROUPS.map((group) => (
          <div className="mb-5" key={group.label}>
            <h4
              className="mb-2 px-2 text-[11px] font-bold uppercase"
              style={{ color: "var(--text-muted)", letterSpacing: "0.09em" }}
            >
              {group.label}
            </h4>
            {group.items.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className="mb-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium no-underline"
                style={({ isActive }) => ({
                  background: isActive ? "color-mix(in srgb, var(--accent) 14%, transparent)" : "transparent",
                  color: isActive ? "var(--accent)" : "var(--text-muted)",
                  fontWeight: isActive ? 600 : 500,
                })}
              >
                <span className="h-1.5 w-1.5 rounded-full" style={{ background: "currentColor", opacity: 0.7 }} />
                {item.label}
              </NavLink>
            ))}
          </div>
        ))}
      </aside>

      <div className="flex flex-col">
        <header
          className="flex items-center justify-between px-7 py-4"
          style={{ borderBottom: "1px solid var(--border)" }}
        >
          <div className="flex items-center gap-2 text-sm font-semibold" style={{ color: "var(--text)" }}>
            <div
              className="font-display flex h-8 w-8 items-center justify-center rounded-full text-xs font-extrabold"
              style={{ background: "var(--accent)", color: "var(--accent-ink)" }}
            >
              {user?.name?.[0]?.toUpperCase() ?? "A"}
            </div>
            {user?.name}
          </div>
          <button
            onClick={logout}
            className="rounded-lg px-3 py-1.5 text-sm font-semibold"
            style={{ background: "var(--surface-2)", color: "var(--text)", border: "1px solid var(--border)" }}
          >
            Log out
          </button>
        </header>

        <main className="flex-1 p-7">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Create the ComingSoon placeholder**

Create `c:\Users\Admin\liftops-frontend\src\components\admin\ComingSoon.jsx`:
```jsx
export default function ComingSoon({ title }) {
  return (
    <div>
      <p className="mb-1 text-xs font-semibold uppercase" style={{ color: "var(--accent)", letterSpacing: "0.09em" }}>
        Admin
      </p>
      <h1 className="font-display mb-2 text-3xl font-extrabold" style={{ color: "var(--text)" }}>
        {title}
      </h1>
      <p className="text-sm" style={{ color: "var(--text-muted)" }}>
        This section is coming soon.
      </p>
    </div>
  );
}
```

- [ ] **Step 3: Delete the superseded flat admin placeholder**

Delete `c:\Users\Admin\liftops-frontend\src\pages\admin\DashboardHome.jsx`.

- [ ] **Step 4: Wire the full admin route tree into App.jsx**

Replace the contents of `c:\Users\Admin\liftops-frontend\src\App.jsx` with:
```jsx
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, useAuth } from "@/auth/AuthContext";
import ProtectedRoute from "@/auth/ProtectedRoute";
import Login from "@/pages/Login";
import AdminLayout from "@/layouts/AdminLayout";
import ComingSoon from "@/components/admin/ComingSoon";
import DriverDashboard from "@/pages/driver/DashboardHome";
import PassengerDashboard from "@/pages/passenger/DashboardHome";

function RoleHomeRedirect() {
  const { user } = useAuth();
  return <Navigate to={`/${user.role}`} replace />;
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />

          <Route element={<ProtectedRoute />}>
            <Route path="/" element={<RoleHomeRedirect />} />
          </Route>

          <Route element={<ProtectedRoute allowedRoles={["admin"]} />}>
            <Route path="/admin" element={<AdminLayout />}>
              <Route index element={<Navigate to="/admin/buses" replace />} />
              <Route path="buses" element={<ComingSoon title="Buses" />} />
              <Route path="stations" element={<ComingSoon title="Stations" />} />
              <Route path="routes" element={<ComingSoon title="Routes" />} />
              <Route path="drivers" element={<ComingSoon title="Drivers" />} />
              <Route path="schedules" element={<ComingSoon title="Schedules" />} />
              <Route path="schedule-days" element={<ComingSoon title="Schedule Days" />} />
              <Route path="maintenance" element={<ComingSoon title="Maintenance" />} />
              <Route path="route-stations" element={<ComingSoon title="Route Stations" />} />
              <Route path="trips" element={<ComingSoon title="Trips" />} />
            </Route>
          </Route>

          <Route element={<ProtectedRoute allowedRoles={["driver"]} />}>
            <Route path="/driver" element={<DriverDashboard />} />
          </Route>

          <Route element={<ProtectedRoute allowedRoles={["passenger"]} />}>
            <Route path="/passenger" element={<PassengerDashboard />} />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
```

Note: `buses` is still `ComingSoon` at this stage — Task 3 swaps it to the real page.

- [ ] **Step 5: Verify the full admin shell**

Run the backend (`/c/Users/Admin/.config/herd/bin/php.bat artisan serve` from `c:\Users\Admin\LiftOps\`) and frontend (`npm run dev` from `c:\Users\Admin\liftops-frontend\`). Log in with a real seeded admin account. Expected: redirected through `/admin` to `/admin/buses`, showing the sidebar (4 groups, 9 links), the topbar with your name and a working Log out button, and "Buses — this section is coming soon." in the main area. Click through all 8 other sidebar links — each shows its own title and the same "coming soon" message, with the clicked link highlighted in the sidebar. Stop both servers when done.

- [ ] **Step 6: Commit**

```bash
cd c:/Users/Admin/liftops-frontend
git add -A
git commit -m "Add admin layout shell with sidebar nav and placeholder pages for all 9 sections"
```

---

### Task 2: Buses API hooks

**Files:**
- Create: `c:\Users\Admin\liftops-frontend\src\api\buses.js`

**Interfaces:**
- Consumes: `api` default export from `@/api/client` (from the Foundation)
- Produces: `useBuses(page)` (query hook, returns TanStack Query's result object — `data` is the raw Laravel pagination response `{ data: [...], current_page, last_page, ... }`), `useCreateBus()`, `useUpdateBus()`, `useDeleteBus()` (mutation hooks, each auto-invalidates the `["buses"]` query on success) — all consumed by Task 3 and Task 4

- [ ] **Step 1: Write the Buses API hooks**

Create `c:\Users\Admin\liftops-frontend\src\api\buses.js`:
```js
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import api from "@/api/client";

export function useBuses(page = 1) {
  return useQuery({
    queryKey: ["buses", page],
    queryFn: async () => {
      const res = await api.get(`/buses?page=${page}`);
      return res.data;
    },
  });
}

export function useCreateBus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload) => {
      const res = await api.post("/buses", payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["buses"] });
    },
  });
}

export function useUpdateBus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const res = await api.put(`/buses/${id}`, payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["buses"] });
    },
  });
}

export function useDeleteBus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/buses/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["buses"] });
    },
  });
}
```

- [ ] **Step 2: Verify useBuses() returns real data**

Temporarily replace the `buses` route's element in `c:\Users\Admin\liftops-frontend\src\App.jsx` — change `<Route path="buses" element={<ComingSoon title="Buses" />} />` to `<Route path="buses" element={<BusesTestProbe />} />`, and temporarily add this component at the top of `App.jsx` (above the `RoleHomeRedirect` function):
```jsx
import { useBuses } from "@/api/buses";

function BusesTestProbe() {
  const { data, isLoading, error } = useBuses(1);
  if (isLoading) return <p>loading...</p>;
  if (error) return <p>error: {error.message}</p>;
  return <pre>{JSON.stringify(data, null, 2)}</pre>;
}
```
Run the backend and `npm run dev`, log in as admin, navigate to `/admin/buses`. Expected: real bus records print as JSON (plate numbers, manufacturers, statuses matching what's actually in the database) — not an error, not empty. Then **revert `App.jsx` back to exactly its Task 1 committed state** (remove the test probe and the `useBuses` import, restore the `ComingSoon` route) — Task 2 should not leave test code committed. Stop both servers.

- [ ] **Step 3: Commit**

```bash
cd c:/Users/Admin/liftops-frontend
git add -A
git commit -m "Add TanStack Query hooks for Buses (list, create, update, delete)"
```

---

### Task 3: Buses list page (read-only)

**Files:**
- Create: `c:\Users\Admin\liftops-frontend\src\pages\admin\buses\BusesPage.jsx`
- Modify: `c:\Users\Admin\liftops-frontend\src\App.jsx`

**Interfaces:**
- Consumes: `useBuses` from `@/api/buses` (Task 2)
- Produces: `BusesPage` (default export, no props) — consumed by `App.jsx`; Task 4 will further modify this same file to add create/edit/delete actions

- [ ] **Step 1: Write the Buses list page**

Create `c:\Users\Admin\liftops-frontend\src\pages\admin\buses\BusesPage.jsx`:
```jsx
import { useState } from "react";
import { useBuses } from "@/api/buses";

const STATUS_STYLE = {
  in_service: { bg: "var(--success-bg)", fg: "var(--success)", label: "In service" },
  maintenance: { bg: "var(--warning-bg)", fg: "var(--warning)", label: "Maintenance" },
  out_of_service: { bg: "var(--critical-bg)", fg: "var(--critical)", label: "Out of service" },
};

export default function BusesPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useBuses(page);

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="font-display text-3xl font-extrabold" style={{ color: "var(--text)" }}>
          Buses
        </h1>
      </div>

      <div className="overflow-x-auto rounded-xl" style={{ background: "var(--surface)", border: "1px solid var(--border)" }}>
        {isLoading && (
          <p className="p-6 text-sm" style={{ color: "var(--text-muted)" }}>
            Loading…
          </p>
        )}
        {isError && (
          <p className="p-6 text-sm" style={{ color: "var(--critical)" }}>
            Failed to load buses.
          </p>
        )}
        {data && (
          <table className="w-full border-collapse">
            <thead>
              <tr style={{ background: "var(--surface-2)" }}>
                {["Plate", "Manufacturer", "Model", "Year", "Capacity", "Status"].map((h) => (
                  <th
                    key={h}
                    className="px-5 py-3 text-left text-[11.5px] font-bold uppercase"
                    style={{ color: "var(--text-muted)", letterSpacing: "0.06em", borderBottom: "1px solid var(--border)" }}
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.data.map((bus) => {
                const status = STATUS_STYLE[bus.status];
                return (
                  <tr key={bus.id} style={{ borderBottom: "1px solid var(--border)" }}>
                    <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                      {bus.plate_number}
                    </td>
                    <td className="px-5 py-3 text-sm" style={{ color: "var(--text)" }}>
                      {bus.manufacturer}
                    </td>
                    <td className="px-5 py-3 text-sm" style={{ color: "var(--text)" }}>
                      {bus.model}
                    </td>
                    <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                      {bus.production_year}
                    </td>
                    <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                      {bus.capacity}
                    </td>
                    <td className="px-5 py-3 text-sm">
                      <span
                        className="rounded-full px-2.5 py-1 text-xs font-semibold"
                        style={{ background: status.bg, color: status.fg }}
                      >
                        {status.label}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      {data && (
        <div className="mt-4 flex items-center justify-between text-sm" style={{ color: "var(--text-muted)" }}>
          <span>
            Page {data.current_page} of {data.last_page}
          </span>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => p - 1)}
              disabled={data.current_page <= 1}
              className="rounded-lg px-3 py-1.5 font-semibold disabled:opacity-40"
              style={{ background: "var(--surface-2)", border: "1px solid var(--border)", color: "var(--text)" }}
            >
              Prev
            </button>
            <button
              onClick={() => setPage((p) => p + 1)}
              disabled={data.current_page >= data.last_page}
              className="rounded-lg px-3 py-1.5 font-semibold disabled:opacity-40"
              style={{ background: "var(--surface-2)", border: "1px solid var(--border)", color: "var(--text)" }}
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Swap the buses route to the real page**

In `c:\Users\Admin\liftops-frontend\src\App.jsx`, add the import `import BusesPage from "@/pages/admin/buses/BusesPage";` near the other page imports, and change `<Route path="buses" element={<ComingSoon title="Buses" />} />` to `<Route path="buses" element={<BusesPage />} />`. Leave the `ComingSoon` import and the other 8 routes unchanged.

- [ ] **Step 3: Verify the real Buses list renders**

Run the backend and `npm run dev`, log in as admin. Expected: `/admin/buses` shows a real table of buses (plate/manufacturer/model/year/capacity/status pill) matching what's actually seeded in the database, with working Prev/Next pagination (Prev disabled on page 1, Next disabled on the last page). Stop both servers.

- [ ] **Step 4: Commit**

```bash
cd c:/Users/Admin/liftops-frontend
git add -A
git commit -m "Add real Buses list page with pagination, replacing its coming-soon placeholder"
```

---

### Task 4: Create, edit, and delete Buses

**Files:**
- Create: `c:\Users\Admin\liftops-frontend\src\pages\admin\buses\BusFormDialog.jsx`
- Modify: `c:\Users\Admin\liftops-frontend\src\pages\admin\buses\BusesPage.jsx`
- Create: `c:\Users\Admin\liftops-frontend\src\components\ui\dialog.jsx` (generated by shadcn CLI)
- Create: `c:\Users\Admin\liftops-frontend\src\components\ui\alert-dialog.jsx` (generated by shadcn CLI)

**Interfaces:**
- Consumes: `useCreateBus`, `useUpdateBus`, `useDeleteBus` from `@/api/buses` (Task 2); `Button`, `Input`, `Label` from `@/components/ui/*` (Foundation); `Dialog`/`DialogContent`/`DialogHeader`/`DialogTitle`/`DialogFooter` and `AlertDialog`/`AlertDialogContent`/`AlertDialogHeader`/`AlertDialogTitle`/`AlertDialogDescription`/`AlertDialogFooter`/`AlertDialogCancel`/`AlertDialogAction` from the new shadcn components added in this task
- Produces: `BusFormDialog` (default export, props: `open` (bool), `onOpenChange` (function), `bus` (object or `null` — `null`/omitted means create mode, an object means edit mode))

- [ ] **Step 1: Add the shadcn dialog and alert-dialog components**

Run (from `c:\Users\Admin\liftops-frontend\`):
```bash
npx shadcn@latest add dialog alert-dialog
```
Expected: the CLI reports 2 components added, creating `src/components/ui/dialog.jsx` and `src/components/ui/alert-dialog.jsx`.

- [ ] **Step 2: Write the create/edit form dialog**

Create `c:\Users\Admin\liftops-frontend\src\pages\admin\buses\BusFormDialog.jsx`:
```jsx
import { useEffect, useState } from "react";
import { useCreateBus, useUpdateBus } from "@/api/buses";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";

const EMPTY_FORM = {
  plate_number: "",
  manufacturer: "",
  model: "",
  production_year: "",
  capacity: "",
  status: "in_service",
};

export default function BusFormDialog({ open, onOpenChange, bus }) {
  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const createBus = useCreateBus();
  const updateBus = useUpdateBus();
  const isEditing = Boolean(bus);
  const isSubmitting = createBus.isPending || updateBus.isPending;

  useEffect(() => {
    if (open) {
      setForm(
        bus
          ? {
              plate_number: bus.plate_number,
              manufacturer: bus.manufacturer,
              model: bus.model,
              production_year: bus.production_year,
              capacity: bus.capacity,
              status: bus.status,
            }
          : EMPTY_FORM
      );
      setErrors({});
    }
  }, [open, bus]);

  function handleChange(field, value) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});
    const payload = {
      ...form,
      production_year: Number(form.production_year),
      capacity: Number(form.capacity),
    };
    try {
      if (isEditing) {
        await updateBus.mutateAsync({ id: bus.id, payload });
      } else {
        await createBus.mutateAsync(payload);
      }
      onOpenChange(false);
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors ?? {});
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? "Edit Bus" : "Add Bus"}</DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="plate_number">Plate number</Label>
            <Input
              id="plate_number"
              value={form.plate_number}
              onChange={(e) => handleChange("plate_number", e.target.value)}
              required
            />
            {errors.plate_number && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.plate_number[0]}
              </p>
            )}
          </div>
          <div>
            <Label htmlFor="manufacturer">Manufacturer</Label>
            <Input
              id="manufacturer"
              value={form.manufacturer}
              onChange={(e) => handleChange("manufacturer", e.target.value)}
              required
            />
            {errors.manufacturer && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.manufacturer[0]}
              </p>
            )}
          </div>
          <div>
            <Label htmlFor="model">Model</Label>
            <Input
              id="model"
              value={form.model}
              onChange={(e) => handleChange("model", e.target.value)}
              required
            />
            {errors.model && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.model[0]}
              </p>
            )}
          </div>
          <div>
            <Label htmlFor="production_year">Production year</Label>
            <Input
              id="production_year"
              type="number"
              value={form.production_year}
              onChange={(e) => handleChange("production_year", e.target.value)}
              required
            />
            {errors.production_year && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.production_year[0]}
              </p>
            )}
          </div>
          <div>
            <Label htmlFor="capacity">Capacity</Label>
            <Input
              id="capacity"
              type="number"
              value={form.capacity}
              onChange={(e) => handleChange("capacity", e.target.value)}
              required
            />
            {errors.capacity && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.capacity[0]}
              </p>
            )}
          </div>
          <div>
            <Label htmlFor="status">Status</Label>
            <select
              id="status"
              value={form.status}
              onChange={(e) => handleChange("status", e.target.value)}
              className="w-full rounded-md border px-3 py-2 text-sm"
              style={{ borderColor: "var(--border)", background: "var(--surface)", color: "var(--text)" }}
            >
              <option value="in_service">In service</option>
              <option value="maintenance">Maintenance</option>
              <option value="out_of_service">Out of service</option>
            </select>
            {errors.status && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.status[0]}
              </p>
            )}
          </div>
          <DialogFooter>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? "Saving…" : "Save"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 3: Wire create/edit/delete into the Buses list page**

Replace the contents of `c:\Users\Admin\liftops-frontend\src\pages\admin\buses\BusesPage.jsx` with:
```jsx
import { useState } from "react";
import { useBuses, useDeleteBus } from "@/api/buses";
import { Button } from "@/components/ui/button";
import BusFormDialog from "./BusFormDialog";
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
  AlertDialogAction,
} from "@/components/ui/alert-dialog";

const STATUS_STYLE = {
  in_service: { bg: "var(--success-bg)", fg: "var(--success)", label: "In service" },
  maintenance: { bg: "var(--warning-bg)", fg: "var(--warning)", label: "Maintenance" },
  out_of_service: { bg: "var(--critical-bg)", fg: "var(--critical)", label: "Out of service" },
};

export default function BusesPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useBuses(page);
  const deleteBus = useDeleteBus();

  const [formOpen, setFormOpen] = useState(false);
  const [editingBus, setEditingBus] = useState(null);
  const [deletingBus, setDeletingBus] = useState(null);

  function openCreate() {
    setEditingBus(null);
    setFormOpen(true);
  }

  function openEdit(bus) {
    setEditingBus(bus);
    setFormOpen(true);
  }

  async function confirmDelete() {
    await deleteBus.mutateAsync(deletingBus.id);
    setDeletingBus(null);
  }

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="font-display text-3xl font-extrabold" style={{ color: "var(--text)" }}>
          Buses
        </h1>
        <Button onClick={openCreate}>Add Bus</Button>
      </div>

      <div className="overflow-x-auto rounded-xl" style={{ background: "var(--surface)", border: "1px solid var(--border)" }}>
        {isLoading && (
          <p className="p-6 text-sm" style={{ color: "var(--text-muted)" }}>
            Loading…
          </p>
        )}
        {isError && (
          <p className="p-6 text-sm" style={{ color: "var(--critical)" }}>
            Failed to load buses.
          </p>
        )}
        {data && (
          <table className="w-full border-collapse">
            <thead>
              <tr style={{ background: "var(--surface-2)" }}>
                {["Plate", "Manufacturer", "Model", "Year", "Capacity", "Status", ""].map((h) => (
                  <th
                    key={h}
                    className="px-5 py-3 text-left text-[11.5px] font-bold uppercase"
                    style={{ color: "var(--text-muted)", letterSpacing: "0.06em", borderBottom: "1px solid var(--border)" }}
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.data.map((bus) => {
                const status = STATUS_STYLE[bus.status];
                return (
                  <tr key={bus.id} style={{ borderBottom: "1px solid var(--border)" }}>
                    <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                      {bus.plate_number}
                    </td>
                    <td className="px-5 py-3 text-sm" style={{ color: "var(--text)" }}>
                      {bus.manufacturer}
                    </td>
                    <td className="px-5 py-3 text-sm" style={{ color: "var(--text)" }}>
                      {bus.model}
                    </td>
                    <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                      {bus.production_year}
                    </td>
                    <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                      {bus.capacity}
                    </td>
                    <td className="px-5 py-3 text-sm">
                      <span
                        className="rounded-full px-2.5 py-1 text-xs font-semibold"
                        style={{ background: status.bg, color: status.fg }}
                      >
                        {status.label}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-sm">
                      <button onClick={() => openEdit(bus)} className="mr-3 font-semibold" style={{ color: "var(--accent)" }}>
                        Edit
                      </button>
                      <button onClick={() => setDeletingBus(bus)} className="font-semibold" style={{ color: "var(--critical)" }}>
                        Delete
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      {data && (
        <div className="mt-4 flex items-center justify-between text-sm" style={{ color: "var(--text-muted)" }}>
          <span>
            Page {data.current_page} of {data.last_page}
          </span>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => p - 1)}
              disabled={data.current_page <= 1}
              className="rounded-lg px-3 py-1.5 font-semibold disabled:opacity-40"
              style={{ background: "var(--surface-2)", border: "1px solid var(--border)", color: "var(--text)" }}
            >
              Prev
            </button>
            <button
              onClick={() => setPage((p) => p + 1)}
              disabled={data.current_page >= data.last_page}
              className="rounded-lg px-3 py-1.5 font-semibold disabled:opacity-40"
              style={{ background: "var(--surface-2)", border: "1px solid var(--border)", color: "var(--text)" }}
            >
              Next
            </button>
          </div>
        </div>
      )}

      <BusFormDialog open={formOpen} onOpenChange={setFormOpen} bus={editingBus} />

      <AlertDialog open={Boolean(deletingBus)} onOpenChange={(open) => !open && setDeletingBus(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this bus?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete {deletingBus?.plate_number}. This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete}>Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialog>
      </AlertDialog>
    </div>
  );
}
```

- [ ] **Step 4: Verify create, edit, and delete against the real backend**

Run the backend and `npm run dev`, log in as admin, go to `/admin/buses`.
1. Click "Add Bus", fill in a real plate number (e.g. one that doesn't already exist) and the other fields, submit. Expected: dialog closes, the new bus appears in the list without a manual refresh.
2. Click "Add Bus" again and submit a plate number that already exists in the database. Expected: the dialog stays open, and an inline red error appears under the "Plate number" field with the backend's actual message (e.g. "The plate number has already been taken.").
3. Click "Edit" on an existing bus, change its status, submit. Expected: dialog closes, the row updates in place.
4. Click "Delete" on a bus, confirm in the alert dialog. Expected: the bus disappears from the list.

Stop both servers when done.

- [ ] **Step 5: Commit**

```bash
cd c:/Users/Admin/liftops-frontend
git add -A
git commit -m "Add create, edit, and delete for Buses via shadcn Dialog and AlertDialog"
```

---

## Self-Review Notes

- **Spec coverage:** modal-based create/edit (Task 4) · full nav with placeholders (Task 1) · plain table + Laravel pagination (Task 3) · plain useState forms matching Login.jsx (Task 4) · inline per-field 422 errors (Task 4) · TanStack Query hooks (Task 2) — all covered.
- **Placeholder scan:** none found — every step has literal code/commands.
- **Type/name consistency checked:** `useBuses`/`useCreateBus`/`useUpdateBus`/`useDeleteBus` export names match between Task 2's definition and Tasks 3-4's imports; `BusFormDialog`'s `{ open, onOpenChange, bus }` prop names match how `BusesPage.jsx` invokes it in Task 4; the `errors` shape (`err.response.data.errors`) matches the same Laravel 422 shape already verified against the real backend during the Foundation plan.
