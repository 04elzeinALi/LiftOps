# Admin Schedules Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the real Schedules admin page — the first resource with a foreign key (`route_id`) — establishing the "FK dropdown populated from another resource" pattern the remaining FK-heavy resources will copy.

**Architecture:** Same list/create/edit/delete shape as the Buses template from the Admin Panel Foundation. The one new piece: `ScheduleFormDialog`'s `route_id` field is a shadcn `Select` populated by a minimal, read-only `useRoutes()` hook, instead of a plain text/enum field.

**Tech Stack:** Same as prior sub-projects (React 19, plain JS, react-router-dom, @tanstack/react-query, Tailwind v4, shadcn/ui) — one new shadcn component (`select`).

## Global Constraints

- Plain JavaScript only — no TypeScript.
- No automated tests — every task is verified by running the dev server against the real backend and checking real behavior.
- All styling uses the existing design tokens (`var(--bg)`, `var(--text)`, `var(--accent)`, etc.) and the `font-display`/`font-mono` Tailwind utilities already set up — no new colors invented.
- Do not install Playwright, Chromium, or any browser-automation/testing tool.
- Do not leave dev servers running or test tokens active after finishing a task — stop servers (checking for orphaned `php.exe` child processes, a known quirk from prior tasks) and revoke any captured tokens before reporting. If MySQL/XAMPP isn't running, it may need starting via `--defaults-file=c:\xampp1\mysql\bin\my.ini` (a known local-environment quirk) — stop it again when done.
- The delete-confirm pattern MUST include the `event.preventDefault()` call (with its explanatory comment) in the `confirmDelete` handler — `AlertDialogAction` auto-closes its dialog via Radix's `DialogPrimitive.Close` otherwise, discovered and fixed the hard way in the Buses template. Do not omit it.
- Every commit happens inside `c:\Users\Admin\liftops-frontend\` — this plan touches only the frontend repo.
- Real working admin account for verification: `elzeinali04@gmail.com` / `password`.

---

### Task 1: Routes (read-only) and Schedules API hooks

**Files:**
- Create: `c:\Users\Admin\liftops-frontend\src\api\routes.js`
- Create: `c:\Users\Admin\liftops-frontend\src\api\schedules.js`

**Interfaces:**
- Consumes: `api` default export from `@/api/client`
- Produces: `useRoutes()` (query hook, returns TanStack Query's result object — `data` is a plain array of route objects, e.g. `{ id, route_name, origin, destination, fare, ... }`); `useSchedules(page)`, `useCreateSchedule()`, `useUpdateSchedule()`, `useDeleteSchedule()` (same shape as `@/api/buses.js`'s hooks) — all consumed by Task 2 and Task 3

- [ ] **Step 1: Write the Routes read-only hook**

Create `c:\Users\Admin\liftops-frontend\src\api\routes.js`:
```js
import { useQuery } from "@tanstack/react-query";
import api from "@/api/client";

export function useRoutes() {
  return useQuery({
    queryKey: ["routes-list"],
    queryFn: async () => {
      const res = await api.get("/routes?page=1");
      return res.data.data;
    },
  });
}
```
Note: this deliberately only fetches page 1 (15 routes) — it exists purely to populate a dropdown, not as a full Routes CRUD API. If the number of routes ever exceeds one page, this hook would need revisiting, but that's out of scope here.

- [ ] **Step 2: Write the Schedules API hooks**

Create `c:\Users\Admin\liftops-frontend\src\api\schedules.js`:
```js
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import api from "@/api/client";

export function useSchedules(page = 1) {
  return useQuery({
    queryKey: ["schedules", page],
    queryFn: async () => {
      const res = await api.get(`/schedules?page=${page}`);
      return res.data;
    },
  });
}

export function useCreateSchedule() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload) => {
      const res = await api.post("/schedules", payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["schedules"] });
    },
  });
}

export function useUpdateSchedule() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const res = await api.put(`/schedules/${id}`, payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["schedules"] });
    },
  });
}

export function useDeleteSchedule() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/schedules/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["schedules"] });
    },
  });
}
```

- [ ] **Step 3: Verify both hooks return real data**

Temporarily replace the `schedules` route's element in `c:\Users\Admin\liftops-frontend\src\App.jsx` — change `<Route path="schedules" element={<ComingSoon title="Schedules" />} />` to `<Route path="schedules" element={<HooksTestProbe />} />`, and temporarily add this component at the top of `App.jsx` (above the `RoleHomeRedirect` function):
```jsx
import { useRoutes } from "@/api/routes";
import { useSchedules } from "@/api/schedules";

function HooksTestProbe() {
  const routes = useRoutes();
  const schedules = useSchedules(1);
  return (
    <pre>
      {JSON.stringify({ routes: routes.data, schedules: schedules.data }, null, 2)}
    </pre>
  );
}
```
Run the backend (`/c/Users/Admin/.config/herd/bin/php.bat artisan serve` from `c:\Users\Admin\LiftOps\`, starting MySQL first if needed) and `npm run dev` from `c:\Users\Admin\liftops-frontend\`. Log in as admin, navigate to `/admin/schedules`. Expected: real routes (with `route_name`/`origin`/`destination`) and real schedules (with nested `route` objects, `departure_time`, `arrival_time`) print as JSON. Then **revert `App.jsx` back to exactly its previous committed state** (remove the test probe and its imports, restore the `ComingSoon` route) — this task should not leave test code committed. Stop both servers (and MySQL if you started it), confirming no orphaned processes via `tasklist`. Revoke any captured token.

- [ ] **Step 4: Commit**

```bash
cd c:/Users/Admin/liftops-frontend
git add -A
git commit -m "Add Routes read-only hook and Schedules API hooks"
```

---

### Task 2: Schedules list page (read-only)

**Files:**
- Create: `c:\Users\Admin\liftops-frontend\src\pages\admin\schedules\SchedulesPage.jsx`
- Modify: `c:\Users\Admin\liftops-frontend\src\App.jsx`

**Interfaces:**
- Consumes: `useSchedules` from `@/api/schedules` (Task 1)
- Produces: `SchedulesPage` (default export, no props) — consumed by `App.jsx`; Task 3 will further modify this same file to add create/edit/delete actions

- [ ] **Step 1: Write the Schedules list page**

Create `c:\Users\Admin\liftops-frontend\src\pages\admin\schedules\SchedulesPage.jsx`:
```jsx
import { useState } from "react";
import { useSchedules } from "@/api/schedules";

export default function SchedulesPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useSchedules(page);

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="font-display text-3xl font-extrabold" style={{ color: "var(--text)" }}>
          Schedules
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
            Failed to load schedules.
          </p>
        )}
        {data && (
          <table className="w-full border-collapse">
            <thead>
              <tr style={{ background: "var(--surface-2)" }}>
                {["Route", "Departure", "Arrival"].map((h) => (
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
              {data.data.map((schedule) => (
                <tr key={schedule.id} style={{ borderBottom: "1px solid var(--border)" }}>
                  <td className="px-5 py-3 text-sm" style={{ color: "var(--text)" }}>
                    {schedule.route?.route_name}
                  </td>
                  <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                    {schedule.departure_time?.slice(0, 5)}
                  </td>
                  <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                    {schedule.arrival_time?.slice(0, 5)}
                  </td>
                </tr>
              ))}
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

- [ ] **Step 2: Swap the schedules route to the real page**

In `c:\Users\Admin\liftops-frontend\src\App.jsx`, add the import `import SchedulesPage from "@/pages/admin/schedules/SchedulesPage";` near the other page imports, and change `<Route path="schedules" element={<ComingSoon title="Schedules" />} />` to `<Route path="schedules" element={<SchedulesPage />} />`. Leave the `ComingSoon` import and the other 7 routes unchanged.

- [ ] **Step 3: Verify the real Schedules list renders**

Run the backend and `npm run dev` (starting MySQL first if needed), log in as admin. Expected: `/admin/schedules` shows a real table with a **Route** column showing actual route names (not IDs), plus Departure/Arrival times in `HH:MM` format, matching what's actually seeded in the database, with working Prev/Next pagination. Stop both servers (and MySQL if started), confirm no orphaned processes, revoke any captured token.

- [ ] **Step 4: Commit**

```bash
cd c:/Users/Admin/liftops-frontend
git add -A
git commit -m "Add real Schedules list page with pagination, replacing its coming-soon placeholder"
```

---

### Task 3: Create, edit, and delete Schedules (with the Route dropdown)

**Files:**
- Create: `c:\Users\Admin\liftops-frontend\src\pages\admin\schedules\ScheduleFormDialog.jsx`
- Modify: `c:\Users\Admin\liftops-frontend\src\pages\admin\schedules\SchedulesPage.jsx`
- Create: `c:\Users\Admin\liftops-frontend\src\components\ui\select.jsx` (generated by shadcn CLI)

**Interfaces:**
- Consumes: `useRoutes` from `@/api/routes` (Task 1); `useCreateSchedule`, `useUpdateSchedule`, `useDeleteSchedule` from `@/api/schedules` (Task 1); `Button`, `Input`, `Label`, `Dialog`/`DialogContent`/`DialogHeader`/`DialogTitle`/`DialogFooter`, `AlertDialog`/`AlertDialogContent`/`AlertDialogHeader`/`AlertDialogTitle`/`AlertDialogDescription`/`AlertDialogFooter`/`AlertDialogCancel`/`AlertDialogAction` (all from the Admin Panel Foundation); `Select`/`SelectContent`/`SelectItem`/`SelectTrigger`/`SelectValue` from the new shadcn component added in this task
- Produces: `ScheduleFormDialog` (default export, props: `open` (bool), `onOpenChange` (function), `schedule` (object or `null` — `null`/omitted means create mode))

- [ ] **Step 1: Add the shadcn select component**

Run (from `c:\Users\Admin\liftops-frontend\`):
```bash
npx shadcn@latest add select
```
Expected: the CLI reports 1 component added, creating `src/components/ui/select.jsx`.

- [ ] **Step 2: Write the create/edit form dialog with the Route dropdown**

Create `c:\Users\Admin\liftops-frontend\src\pages\admin\schedules\ScheduleFormDialog.jsx`:
```jsx
import { useEffect, useState } from "react";
import { useCreateSchedule, useUpdateSchedule } from "@/api/schedules";
import { useRoutes } from "@/api/routes";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const EMPTY_FORM = {
  route_id: "",
  departure_time: "",
  arrival_time: "",
};

export default function ScheduleFormDialog({ open, onOpenChange, schedule }) {
  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [generalError, setGeneralError] = useState("");
  const { data: routes } = useRoutes();
  const createSchedule = useCreateSchedule();
  const updateSchedule = useUpdateSchedule();
  const isEditing = Boolean(schedule);
  const isSubmitting = createSchedule.isPending || updateSchedule.isPending;

  useEffect(() => {
    if (open) {
      setForm(
        schedule
          ? {
              route_id: schedule.route_id,
              departure_time: schedule.departure_time?.slice(0, 5) ?? "",
              arrival_time: schedule.arrival_time?.slice(0, 5) ?? "",
            }
          : EMPTY_FORM
      );
      setErrors({});
      setGeneralError("");
    }
  }, [open, schedule]);

  function handleChange(field, value) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setErrors({});
    setGeneralError("");
    const payload = {
      route_id: Number(form.route_id),
      departure_time: form.departure_time,
      arrival_time: form.arrival_time,
    };
    try {
      if (isEditing) {
        await updateSchedule.mutateAsync({ id: schedule.id, payload });
      } else {
        await createSchedule.mutateAsync(payload);
      }
      onOpenChange(false);
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors ?? {});
      } else {
        setGeneralError("Something went wrong. Please try again.");
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? "Edit Schedule" : "Add Schedule"}</DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="route_id">Route</Label>
            <Select
              value={form.route_id ? String(form.route_id) : ""}
              onValueChange={(value) => handleChange("route_id", Number(value))}
            >
              <SelectTrigger id="route_id" className="w-full">
                <SelectValue placeholder="Select a route" />
              </SelectTrigger>
              <SelectContent>
                {routes?.map((route) => (
                  <SelectItem key={route.id} value={String(route.id)}>
                    {route.origin} → {route.destination}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.route_id && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.route_id[0]}
              </p>
            )}
          </div>
          <div>
            <Label htmlFor="departure_time">Departure time</Label>
            <Input
              id="departure_time"
              type="time"
              value={form.departure_time}
              onChange={(e) => handleChange("departure_time", e.target.value)}
              required
            />
            {errors.departure_time && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.departure_time[0]}
              </p>
            )}
          </div>
          <div>
            <Label htmlFor="arrival_time">Arrival time</Label>
            <Input
              id="arrival_time"
              type="time"
              value={form.arrival_time}
              onChange={(e) => handleChange("arrival_time", e.target.value)}
              required
            />
            {errors.arrival_time && (
              <p className="mt-1 text-xs" style={{ color: "var(--critical)" }}>
                {errors.arrival_time[0]}
              </p>
            )}
          </div>
          {generalError && (
            <p className="text-sm" style={{ color: "var(--critical)" }}>
              {generalError}
            </p>
          )}
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

- [ ] **Step 3: Wire create/edit/delete into the Schedules list page**

Replace the contents of `c:\Users\Admin\liftops-frontend\src\pages\admin\schedules\SchedulesPage.jsx` with:
```jsx
import { useState } from "react";
import { useDeleteSchedule, useSchedules } from "@/api/schedules";
import { Button } from "@/components/ui/button";
import ScheduleFormDialog from "./ScheduleFormDialog";
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

export default function SchedulesPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useSchedules(page);
  const deleteSchedule = useDeleteSchedule();

  const [formOpen, setFormOpen] = useState(false);
  const [editingSchedule, setEditingSchedule] = useState(null);
  const [deletingSchedule, setDeletingSchedule] = useState(null);
  const [deleteError, setDeleteError] = useState("");

  function openCreate() {
    setEditingSchedule(null);
    setFormOpen(true);
  }

  function openEdit(schedule) {
    setEditingSchedule(schedule);
    setFormOpen(true);
  }

  async function confirmDelete(event) {
    // AlertDialogAction is Radix's DialogPrimitive.Close under the hood, so it
    // auto-closes on click unless we preventDefault() before the first await —
    // without this, the dialog closes immediately regardless of whether the
    // delete succeeds, and the error message below can never be seen.
    event.preventDefault();
    setDeleteError("");
    try {
      await deleteSchedule.mutateAsync(deletingSchedule.id);
      setDeletingSchedule(null);
    } catch {
      setDeleteError("Failed to delete this schedule. Please try again.");
    }
  }

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="font-display text-3xl font-extrabold" style={{ color: "var(--text)" }}>
          Schedules
        </h1>
        <Button onClick={openCreate}>Add Schedule</Button>
      </div>

      <div className="overflow-x-auto rounded-xl" style={{ background: "var(--surface)", border: "1px solid var(--border)" }}>
        {isLoading && (
          <p className="p-6 text-sm" style={{ color: "var(--text-muted)" }}>
            Loading…
          </p>
        )}
        {isError && (
          <p className="p-6 text-sm" style={{ color: "var(--critical)" }}>
            Failed to load schedules.
          </p>
        )}
        {data && (
          <table className="w-full border-collapse">
            <thead>
              <tr style={{ background: "var(--surface-2)" }}>
                {["Route", "Departure", "Arrival", ""].map((h) => (
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
              {data.data.map((schedule) => (
                <tr key={schedule.id} style={{ borderBottom: "1px solid var(--border)" }}>
                  <td className="px-5 py-3 text-sm" style={{ color: "var(--text)" }}>
                    {schedule.route?.route_name}
                  </td>
                  <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                    {schedule.departure_time?.slice(0, 5)}
                  </td>
                  <td className="px-5 py-3 text-sm" style={{ fontFamily: "var(--font-mono)" }}>
                    {schedule.arrival_time?.slice(0, 5)}
                  </td>
                  <td className="px-5 py-3 text-sm">
                    <button onClick={() => openEdit(schedule)} className="mr-3 font-semibold" style={{ color: "var(--accent)" }}>
                      Edit
                    </button>
                    <button onClick={() => setDeletingSchedule(schedule)} className="font-semibold" style={{ color: "var(--critical)" }}>
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
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

      <ScheduleFormDialog open={formOpen} onOpenChange={setFormOpen} schedule={editingSchedule} />

      <AlertDialog
        open={Boolean(deletingSchedule)}
        onOpenChange={(open) => {
          if (!open) {
            setDeletingSchedule(null);
            setDeleteError("");
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this schedule?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete this schedule. This action cannot be undone.
            </AlertDialogDescription>
            {deleteError && (
              <p className="mt-2 text-sm" style={{ color: "var(--critical)" }}>
                {deleteError}
              </p>
            )}
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete}>Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
```

- [ ] **Step 4: Verify create, edit, and delete against the real backend, including the Route dropdown**

Run the backend (starting MySQL first if needed) and `npm run dev`, log in as admin, go to `/admin/schedules`.
1. Click "Add Schedule". Confirm the Route dropdown lists real routes labeled `"{origin} → {destination}"` (not raw IDs). Pick one, fill in departure/arrival times, submit. Expected: dialog closes, the new schedule appears in the list with the correct route name shown.
2. Click "Add Schedule" again, leave the Route unselected, try to submit with an invalid/missing `route_id` (e.g. submit without picking one, if the browser's `required` doesn't block it — otherwise pick a route then verify a 422 case some other way, e.g. by temporarily testing with a route_id that doesn't exist via direct API interaction). Expected: an inline error appears under "Route" with the backend's actual validation message.
3. Click "Edit" on an existing schedule, confirm the dropdown pre-selects its current route, change the departure time, submit. Expected: dialog closes, the row updates in place.
4. Click "Delete" on a schedule, confirm in the alert dialog. Expected: the schedule disappears from the list.

Stop both servers (and MySQL if started), confirm no orphaned processes via `tasklist`, revoke any captured token.

- [ ] **Step 5: Commit**

```bash
cd c:/Users/Admin/liftops-frontend
git add -A
git commit -m "Add create, edit, and delete for Schedules with a Route dropdown via shadcn Select"
```

---

## Self-Review Notes

- **Spec coverage:** shadcn Select for the Route dropdown (Task 3) · minimal read-only `useRoutes()` (Task 1) · Route displayed via the already-eager-loaded `schedule.route` (Tasks 2-3) · `<input type="time">` for time fields (Task 3) · same list/pagination/CRUD/error-handling shape as Buses, including the `event.preventDefault()` fix with its comment carried forward from day one (Task 3) — all covered.
- **Placeholder scan:** none found — every step has literal code/commands.
- **Type/name consistency checked:** `useRoutes`/`useSchedules`/`useCreateSchedule`/`useUpdateSchedule`/`useDeleteSchedule` export names match between Task 1's definitions and Tasks 2-3's imports; `ScheduleFormDialog`'s `{ open, onOpenChange, schedule }` prop names match how `SchedulesPage.jsx` invokes it in Task 3; `route.origin`/`route.destination`/`route.id` field names match the real `routes` table schema (confirmed against the existing `RouteController`/`Route` model conventions used elsewhere in this project); the `errors`/`generalError`/`deleteError` shapes match the same pattern already proven in `BusFormDialog`/`BusesPage`.
